<?php

namespace BarefootEngine\Services;

use BarefootEngine\Properties\Property_Parser;
use BarefootEngine\Properties\Property_Post_Type;
use BarefootEngine\Properties\Property_Taxonomies;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Sync_Service
{
    public const SYNC_STATE_OPTION_KEY = 'barefoot_engine_property_sync_state';
    private const QUERYABLE_META_INDEX_KEY = '_be_property_queryable_meta_keys';
    private const QUERYABLE_META_PREFIX = '_be_property_api_';
    private const CURATED_FIELD_ORDER = [
        'PropertyID',
        'name',
        'extdescription',
        'description',
        'status',
        'PropertyType',
        'Longitude',
        'Latitude',
        'PropertyTitle',
        'SleepsBeds',
        'NumberFloors',
        'UnitType',
        'propAddress',
        'propAddressNew',
        'street',
        'street2',
        'city',
        'state',
        'zip',
        'country',
        'a259',
        'a261',
        'a267',
        'amenities',
    ];

    private Barefoot_Api_Client $api_client;
    private Api_Integration_Settings $api_settings;
    private Property_Parser $parser;
    private Property_Taxonomies $property_taxonomies;

    public function __construct(
        ?Barefoot_Api_Client $api_client = null,
        ?Api_Integration_Settings $api_settings = null,
        ?Property_Parser $parser = null,
        ?Property_Taxonomies $property_taxonomies = null
    ) {
        $this->api_client = $api_client ?? new Barefoot_Api_Client();
        $this->api_settings = $api_settings ?? new Api_Integration_Settings();
        $this->parser = $parser ?? new Property_Parser();
        $this->property_taxonomies = $property_taxonomies ?? new Property_Taxonomies();
    }

    /**
     * @return array<string, mixed>
     */
    public function get_sync_state(): array
    {
        $stored = get_option(self::SYNC_STATE_OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $state = array_merge($this->get_default_state(), $stored);
        $state['summary'] = $this->normalize_summary($state['summary'] ?? []);
        $state['field_keys'] = $this->normalize_field_keys($state['field_keys'] ?? []);
        $state['amenity_labels'] = $this->normalize_amenity_labels($state['amenity_labels'] ?? []);
        $state['amenity_types'] = $this->normalize_amenity_types($state['amenity_types'] ?? []);
        $state['progress'] = $this->normalize_progress($state['progress'] ?? []);
        $state['last_error'] = isset($state['last_error']) && is_string($state['last_error']) ? $state['last_error'] : '';
        $state['last_status'] = isset($state['last_status']) && is_string($state['last_status']) ? $state['last_status'] : 'idle';
        $state['last_sync_mode'] = isset($state['last_sync_mode']) && is_string($state['last_sync_mode']) ? $state['last_sync_mode'] : 'none';
        $state['last_started_at'] = isset($state['last_started_at']) ? (int) $state['last_started_at'] : 0;
        $state['last_finished_at'] = isset($state['last_finished_at']) ? (int) $state['last_finished_at'] : 0;
        $state['last_full_started_at'] = isset($state['last_full_started_at']) ? (int) $state['last_full_started_at'] : 0;
        $state['last_full_finished_at'] = isset($state['last_full_finished_at']) ? (int) $state['last_full_finished_at'] : 0;
        $state['last_full_status'] = isset($state['last_full_status']) && is_string($state['last_full_status'])
            ? $state['last_full_status']
            : 'idle';
        $state['last_started_human'] = $this->format_timestamp($state['last_started_at']);
        $state['last_finished_human'] = $this->format_timestamp($state['last_finished_at']);
        $state['last_full_started_human'] = $this->format_timestamp($state['last_full_started_at']);
        $state['last_full_finished_human'] = $this->format_timestamp($state['last_full_finished_at']);

        return $state;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function sync(): array|WP_Error
    {
        return $this->run_full_sync();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function sync_partial(): array|WP_Error
    {
        return $this->run_partial_sync();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function sync_single_post(int $post_id): array|WP_Error
    {
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post || $post->post_type !== Property_Post_Type::POST_TYPE) {
            return new WP_Error(
                'barefoot_engine_property_not_found',
                __('The selected property post could not be found.', 'barefoot-engine'),
                ['status' => 404]
            );
        }

        $property_id = (string) get_post_meta($post_id, '_be_property_id', true);
        if ($property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('This property does not have a stored Barefoot Property ID yet.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->sync_single_property_id($property_id, $post_id);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function run_full_sync(): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_property_missing_credentials',
                __('Please save your Barefoot API credentials before syncing properties.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $started_at = time();
        $state = $this->initialize_running_state($this->get_sync_state(), $started_at, 'full');

        $amenity_state = $this->resolve_amenity_state($settings, $state, true);
        if (is_wp_error($amenity_state)) {
            return $this->fail_sync($started_at, $state, $amenity_state, 'full');
        }

        $amenity_labels = $amenity_state['amenity_labels'];
        $amenity_types = $amenity_state['amenity_types'];
        $state['amenity_labels'] = $amenity_labels;
        $state['amenity_types'] = $amenity_types;
        $this->persist_state($state);

        $property_xml = $this->api_client->fetch_property_ext_xml($settings);
        if (is_wp_error($property_xml)) {
            return $this->fail_sync($started_at, $state, $property_xml, 'full');
        }

        $parsed = $this->parser->parse_property_list($property_xml);
        if (is_wp_error($parsed)) {
            return $this->fail_sync($started_at, $state, $parsed, 'full');
        }

        $existing = $this->get_existing_post_map();
        $seen_property_ids = [];
        $field_keys = isset($parsed['field_keys']) && is_array($parsed['field_keys'])
            ? $this->normalize_field_keys($parsed['field_keys'])
            : [];

        $summary = $this->normalize_summary([]);
        $summary['started_at'] = $started_at;
        $summary['skipped_items'] = [];

        $properties = isset($parsed['properties']) && is_array($parsed['properties']) ? $parsed['properties'] : [];
        $properties = $this->attach_property_images($properties, $settings, $state, 'full');
        if (is_wp_error($properties)) {
            return $this->fail_sync($started_at, $state, $properties, 'full');
        }

        $this->update_progress(
            $state,
            [
                'stage' => 'upserting_properties',
                'current' => count($properties),
                'total' => count($properties),
                'message' => __('Applying property updates and taxonomy terms…', 'barefoot-engine'),
                'current_property_id' => '',
                'current_property_title' => '',
            ]
        );

        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }

            $property_id = isset($property['property_id']) && is_string($property['property_id'])
                ? trim($property['property_id'])
                : '';

            if ($property_id === '') {
                $summary['skipped']++;
                $summary['skipped_items'][] = [
                    'reason' => 'missing_property_id',
                    'title' => isset($property['title']) && is_string($property['title']) ? $property['title'] : '',
                ];
                continue;
            }

            $seen_property_ids[$property_id] = true;
            $existing_record = $existing[$property_id] ?? null;
            $result = $this->sync_property_payload($existing_record, $property, $started_at, $amenity_labels, $amenity_types);

            if (is_wp_error($result)) {
                $summary['skipped']++;
                $summary['skipped_items'][] = [
                    'property_id' => $property_id,
                    'reason' => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ];
                continue;
            }

            $summary[$result['result']]++;
        }

        foreach ($existing as $property_id => $record) {
            if (isset($seen_property_ids[$property_id])) {
                continue;
            }

            $did_deactivate = $this->mark_property_missing((int) $record['post_id'], $record);
            if ($did_deactivate) {
                $summary['deactivated']++;
            }
        }

        $summary['total_seen'] = count($seen_property_ids);
        $summary['finished_at'] = time();

        $this->update_progress(
            $state,
            [
                'stage' => 'finalizing',
                'current' => $summary['total_seen'],
                'total' => $summary['total_seen'],
                'message' => __('Finalizing sync state…', 'barefoot-engine'),
                'current_property_id' => '',
                'current_property_title' => '',
            ]
        );

        $final_state = $this->build_success_state(
            $state,
            $started_at,
            $summary,
            $field_keys,
            $amenity_labels,
            $amenity_types,
            'full'
        );
        $this->persist_state($final_state);

        return [
            'summary' => $summary,
            'sync_state' => $this->get_sync_state(),
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function run_partial_sync(): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_property_missing_credentials',
                __('Please save your Barefoot API credentials before syncing properties.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $current_state = $this->get_sync_state();
        $last_full_finished_at = isset($current_state['last_full_finished_at']) ? (int) $current_state['last_full_finished_at'] : 0;
        if ($last_full_finished_at <= 0) {
            return new WP_Error(
                'barefoot_engine_partial_sync_requires_full_sync',
                __('Run a successful full sync before using Partial Sync.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $started_at = time();
        $state = $this->initialize_running_state($current_state, $started_at, 'partial');

        $amenity_state = $this->resolve_amenity_state($settings, $state, false);
        if (is_wp_error($amenity_state)) {
            return $this->fail_sync($started_at, $state, $amenity_state, 'partial');
        }

        $amenity_labels = $amenity_state['amenity_labels'];
        $amenity_types = $amenity_state['amenity_types'];
        $state['amenity_labels'] = $amenity_labels;
        $state['amenity_types'] = $amenity_types;
        $this->persist_state($state);

        $this->update_progress(
            $state,
            [
                'stage' => 'fetching_ids',
                'message' => __('Fetching recently updated Barefoot property IDs…', 'barefoot-engine'),
            ]
        );

        $updated_ids_string = $this->api_client->fetch_last_updated_property_ids_string(
            $settings,
            $this->format_last_access_time($last_full_finished_at)
        );
        if (is_wp_error($updated_ids_string)) {
            return $this->fail_sync($started_at, $state, $updated_ids_string, 'partial');
        }

        $property_ids = $this->parser->parse_last_updated_property_ids($updated_ids_string);
        if (is_wp_error($property_ids)) {
            return $this->fail_sync($started_at, $state, $property_ids, 'partial');
        }

        $summary = $this->normalize_summary([]);
        $summary['started_at'] = $started_at;
        $summary['skipped_items'] = [];
        $summary['total_seen'] = count($property_ids);
        $field_keys = $this->normalize_field_keys($state['field_keys'] ?? []);
        $existing = $this->get_existing_post_map();

        if ($property_ids === []) {
            $summary['finished_at'] = time();

            $final_state = $this->build_success_state(
                $state,
                $started_at,
                $summary,
                $field_keys,
                $amenity_labels,
                $amenity_types,
                'partial'
            );
            $this->persist_state($final_state);

            return [
                'summary' => $summary,
                'sync_state' => $this->get_sync_state(),
            ];
        }

        $this->update_progress(
            $state,
            [
                'stage' => 'fetching_images',
                'current' => 0,
                'total' => count($property_ids),
                'message' => sprintf(
                    /* translators: %d: total properties to process in partial sync */
                    __('Syncing property 0 of %d…', 'barefoot-engine'),
                    count($property_ids)
                ),
            ]
        );

        $completed = 0;

        foreach ($property_ids as $property_id) {
            $property = $this->fetch_property_details_payload($settings, $property_id);
            if (is_wp_error($property)) {
                $summary['skipped']++;
                $summary['skipped_items'][] = [
                    'property_id' => $property_id,
                    'reason' => $property->get_error_code(),
                    'message' => $property->get_error_message(),
                ];
                $completed++;
                $this->update_progress(
                    $state,
                    [
                        'current' => $completed,
                        'total' => count($property_ids),
                        'message' => $this->build_progress_message($completed, count($property_ids)),
                        'current_property_id' => '',
                        'current_property_title' => '',
                    ]
                );
                continue;
            }

            $field_keys = $this->normalize_field_keys(array_merge($field_keys, array_keys($property['fields'] ?? [])));
            $property = $this->enrich_property_with_images($property, $settings, $state, 'partial', $completed, count($property_ids));
            if (is_wp_error($property)) {
                $summary['skipped']++;
                $summary['skipped_items'][] = [
                    'property_id' => $property_id,
                    'reason' => $property->get_error_code(),
                    'message' => $property->get_error_message(),
                ];
                $completed++;
                $this->update_progress(
                    $state,
                    [
                        'current' => $completed,
                        'total' => count($property_ids),
                        'message' => $this->build_progress_message($completed, count($property_ids)),
                        'current_property_id' => '',
                        'current_property_title' => '',
                    ]
                );
                continue;
            }

            $existing_record = $existing[$property_id] ?? null;
            $result = $this->sync_property_payload($existing_record, $property, $started_at, $amenity_labels, $amenity_types);
            if (is_wp_error($result)) {
                $summary['skipped']++;
                $summary['skipped_items'][] = [
                    'property_id' => $property_id,
                    'reason' => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                ];
            } else {
                $summary[$result['result']]++;
            }

            $completed++;
            $this->update_progress(
                $state,
                [
                    'current' => $completed,
                    'total' => count($property_ids),
                    'message' => $this->build_progress_message($completed, count($property_ids), $property_id, $property['title'] ?? ''),
                    'current_property_id' => $property_id,
                    'current_property_title' => isset($property['title']) && is_string($property['title']) ? $property['title'] : '',
                ]
            );
        }

        $this->update_progress(
            $state,
            [
                'stage' => 'finalizing',
                'current' => count($property_ids),
                'total' => count($property_ids),
                'message' => __('Finalizing partial sync state…', 'barefoot-engine'),
                'current_property_id' => '',
                'current_property_title' => '',
            ]
        );

        $summary['finished_at'] = time();

        $final_state = $this->build_success_state(
            $state,
            $started_at,
            $summary,
            $field_keys,
            $amenity_labels,
            $amenity_types,
            'partial'
        );
        $this->persist_state($final_state);

        return [
            'summary' => $summary,
            'sync_state' => $this->get_sync_state(),
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function sync_single_property_id(string $property_id, int $post_id): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_property_missing_credentials',
                __('Please save your Barefoot API credentials before syncing properties.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $amenity_state = $this->resolve_amenity_state($settings, $this->get_sync_state(), false);
        if (is_wp_error($amenity_state)) {
            return $amenity_state;
        }

        $amenity_labels = $amenity_state['amenity_labels'];
        $amenity_types = $amenity_state['amenity_types'];
        $property = $this->fetch_property_details_payload($settings, $property_id);

        if ($property instanceof WP_Error && $property->get_error_code() === 'barefoot_engine_property_not_returned') {
            $record = [
                'post_id' => $post_id,
                'import_status' => (string) get_post_meta($post_id, '_be_property_import_status', true),
                'post_status' => get_post_status($post_id) ?: 'draft',
            ];

            $this->mark_property_missing($post_id, $record);

            return [
                'result' => 'missing',
                'property_id' => $property_id,
                'post_id' => $post_id,
                'message' => __('This property is no longer returned by Barefoot as an active property and was marked as missing.', 'barefoot-engine'),
            ];
        }

        if (is_wp_error($property)) {
            return $property;
        }

        $property = $this->enrich_property_with_images($property, $settings);
        if (is_wp_error($property)) {
            return $property;
        }

        $existing = [
            'post_id' => $post_id,
            'source_hash' => (string) get_post_meta($post_id, '_be_property_source_hash', true),
            'import_status' => (string) get_post_meta($post_id, '_be_property_import_status', true),
            'post_status' => get_post_status($post_id) ?: 'draft',
            'title' => get_the_title($post_id),
        ];

        $result = $this->sync_property_payload($existing, $property, time(), $amenity_labels, $amenity_types);
        if (is_wp_error($result)) {
            return $result;
        }

        $state = $this->get_sync_state();
        $state['amenity_labels'] = $amenity_labels;
        $state['amenity_types'] = $amenity_types;
        $state['field_keys'] = $this->normalize_field_keys(array_merge($state['field_keys'] ?? [], array_keys($property['fields'] ?? [])));
        $this->persist_state($state);

        return [
            'result' => $result['result'],
            'property_id' => $property_id,
            'post_id' => $post_id,
            'message' => $result['result'] === 'unchanged'
                ? __('This property is already up to date.', 'barefoot-engine')
                : __('This property was synced successfully.', 'barefoot-engine'),
        ];
    }

    /**
     * @param array<string, mixed> $state
     * @return array{amenity_labels: array<string, string>, amenity_types: array<string, string>}|WP_Error
     */
    private function resolve_amenity_state(array $settings, array $state, bool $refresh): array|WP_Error
    {
        $amenity_labels = isset($state['amenity_labels']) && is_array($state['amenity_labels'])
            ? $this->normalize_amenity_labels($state['amenity_labels'])
            : [];
        $amenity_types = isset($state['amenity_types']) && is_array($state['amenity_types'])
            ? $this->normalize_amenity_types($state['amenity_types'])
            : [];

        if (!$refresh && $amenity_labels !== [] && $amenity_types !== []) {
            return [
                'amenity_labels' => $amenity_labels,
                'amenity_types' => $amenity_types,
            ];
        }

        $amenity_definitions = $this->api_client->fetch_amenity_definitions($settings);
        if (is_wp_error($amenity_definitions)) {
            return $amenity_definitions;
        }

        return [
            'amenity_labels' => isset($amenity_definitions['labels']) && is_array($amenity_definitions['labels'])
                ? $this->normalize_amenity_labels($amenity_definitions['labels'])
                : [],
            'amenity_types' => isset($amenity_definitions['types']) && is_array($amenity_definitions['types'])
                ? $this->normalize_amenity_types($amenity_definitions['types'])
                : [],
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function fetch_property_details_payload(array $settings, string $property_id): array|WP_Error
    {
        $property_xml = $this->api_client->fetch_property_details_xml($settings, $property_id);
        if (is_wp_error($property_xml)) {
            return $property_xml;
        }

        $parsed = $this->parser->parse_property_details($property_xml);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        $resolved_property_id = isset($parsed['property_id']) && is_string($parsed['property_id'])
            ? trim($parsed['property_id'])
            : '';
        if ($resolved_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_not_returned',
                sprintf(
                    /* translators: %s: Barefoot property ID */
                    __('Barefoot did not return a property payload for property %s.', 'barefoot-engine'),
                    $property_id
                ),
                ['status' => 404]
            );
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, array<string, string>> $settings
     * @param array<string, mixed>|null $state
     * @return array<string, mixed>|WP_Error
     */
    private function enrich_property_with_images(
        array $property,
        array $settings,
        ?array &$state = null,
        string $mode = 'single',
        int $completed = 0,
        int $total = 0
    ): array|WP_Error {
        $property_id = isset($property['property_id']) && is_string($property['property_id'])
            ? trim($property['property_id'])
            : '';
        $title = isset($property['title']) && is_string($property['title']) ? $property['title'] : '';

        if ($state !== null && $total > 0) {
            $this->update_progress(
                $state,
                [
                    'stage' => 'fetching_images',
                    'current' => $completed,
                    'total' => $total,
                    'message' => $this->build_progress_message($completed, $total, $property_id, $title),
                    'current_property_id' => $property_id,
                    'current_property_title' => $title,
                    'mode' => $mode,
                    'active' => true,
                ]
            );
        }

        $images = [];
        $images_raw_xml = '';

        if ($property_id !== '') {
            $images_xml = $this->api_client->fetch_property_images_xml($settings, $property_id);
            if (is_wp_error($images_xml)) {
                return new WP_Error(
                    $images_xml->get_error_code(),
                    sprintf(
                        /* translators: %s: Barefoot property ID */
                        __('Unable to fetch images for property %s.', 'barefoot-engine'),
                        $property_id
                    ) . ' ' . $images_xml->get_error_message(),
                    $images_xml->get_error_data()
                );
            }

            $parsed_images = $this->parser->parse_property_images($images_xml);
            if (is_wp_error($parsed_images)) {
                return new WP_Error(
                    $parsed_images->get_error_code(),
                    sprintf(
                        /* translators: %s: Barefoot property ID */
                        __('Unable to parse images for property %s.', 'barefoot-engine'),
                        $property_id
                    ) . ' ' . $parsed_images->get_error_message(),
                    $parsed_images->get_error_data()
                );
            }

            $images = isset($parsed_images['images']) && is_array($parsed_images['images'])
                ? array_values($parsed_images['images'])
                : [];
            $images_raw_xml = isset($parsed_images['raw_xml']) && is_string($parsed_images['raw_xml'])
                ? $parsed_images['raw_xml']
                : '';
        }

        $raw_xml = isset($property['raw_xml']) && is_string($property['raw_xml']) ? $property['raw_xml'] : '';
        $property['images'] = $images;
        $property['images_raw_xml'] = $images_raw_xml;
        $property['source_hash'] = $this->build_property_source_hash($raw_xml, $images_raw_xml);

        return $property;
    }

    /**
     * @param array<int, mixed> $properties
     * @param array<string, array<string, string>> $settings
     * @param array<string, mixed> $state
     * @return array<int, array<string, mixed>>|WP_Error
     */
    private function attach_property_images(array $properties, array $settings, array &$state, string $mode): array|WP_Error
    {
        $enriched = [];
        $total = count($properties);
        $completed = 0;

        $this->update_progress(
            $state,
            [
                'active' => true,
                'mode' => $mode,
                'stage' => 'fetching_images',
                'current' => 0,
                'total' => $total,
                'message' => $this->build_progress_message(0, $total),
                'current_property_id' => '',
                'current_property_title' => '',
            ]
        );

        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }

            $enriched_property = $this->enrich_property_with_images($property, $settings, $state, $mode, $completed, $total);
            if (is_wp_error($enriched_property)) {
                return $enriched_property;
            }

            $enriched[] = $enriched_property;
            $completed++;

            $this->update_progress(
                $state,
                [
                    'current' => $completed,
                    'total' => $total,
                    'message' => $this->build_progress_message(
                        $completed,
                        $total,
                        isset($enriched_property['property_id']) && is_string($enriched_property['property_id']) ? $enriched_property['property_id'] : '',
                        isset($enriched_property['title']) && is_string($enriched_property['title']) ? $enriched_property['title'] : ''
                    ),
                    'current_property_id' => isset($enriched_property['property_id']) && is_string($enriched_property['property_id']) ? $enriched_property['property_id'] : '',
                    'current_property_title' => isset($enriched_property['title']) && is_string($enriched_property['title']) ? $enriched_property['title'] : '',
                ]
            );
        }

        return $enriched;
    }

    /**
     * @param array<string, mixed>|null $existing_record
     * @param array<string, mixed> $property
     * @return array<string, int|string>|WP_Error
     */
    private function sync_property_payload(
        ?array $existing_record,
        array $property,
        int $timestamp,
        array $amenity_labels,
        array $amenity_types
    ): array|WP_Error {
        $result = $existing_record === null
            ? $this->create_property_post($property, $timestamp, $amenity_labels, $amenity_types)
            : $this->update_property_post((int) $existing_record['post_id'], $existing_record, $property, $timestamp, $amenity_labels, $amenity_types);

        if (is_wp_error($result)) {
            return $result;
        }

        $post_id = isset($result['post_id']) ? (int) $result['post_id'] : 0;
        $storage = $this->build_storage_payload($post_id, $property, $amenity_labels, $amenity_types);
        $amenities = isset($storage['fields']['amenities']) && is_array($storage['fields']['amenities'])
            ? $storage['fields']['amenities']
            : [];

        $this->property_taxonomies->sync_terms_for_property($post_id, $storage['fields'], $amenities);

        return $result;
    }

    /**
     * @param array<string, mixed> $previous_state
     */
    private function initialize_running_state(array $previous_state, int $started_at, string $mode): array
    {
        $state = $previous_state;
        $state['last_started_at'] = $started_at;
        $state['last_status'] = 'running';
        $state['last_error'] = '';
        $state['last_sync_mode'] = $mode;
        $state['progress'] = array_merge(
            $this->get_default_progress(),
            [
                'active' => true,
                'mode' => $mode,
                'stage' => 'preparing',
                'current' => 0,
                'total' => 0,
                'message' => __('Preparing Barefoot property sync…', 'barefoot-engine'),
            ]
        );

        if ($mode === 'full') {
            $state['last_full_started_at'] = $started_at;
            $state['last_full_status'] = 'running';
        }

        $this->persist_state($state);

        return $state;
    }

    /**
     * @param array<string, mixed> $state
     * @param array<string, mixed> $summary
     * @param array<int, string> $field_keys
     * @param array<string, string> $amenity_labels
     * @param array<string, string> $amenity_types
     * @return array<string, mixed>
     */
    private function build_success_state(
        array $state,
        int $started_at,
        array $summary,
        array $field_keys,
        array $amenity_labels,
        array $amenity_types,
        string $mode
    ): array {
        $finished_at = isset($summary['finished_at']) ? (int) $summary['finished_at'] : time();

        $state['last_started_at'] = $started_at;
        $state['last_finished_at'] = $finished_at;
        $state['last_status'] = 'success';
        $state['last_error'] = '';
        $state['last_sync_mode'] = $mode;
        $state['summary'] = $summary;
        $state['field_keys'] = $field_keys;
        $state['amenity_labels'] = $amenity_labels;
        $state['amenity_types'] = $amenity_types;
        $state['progress'] = array_merge(
            $this->get_default_progress(),
            [
                'active' => false,
                'mode' => $mode,
                'stage' => 'complete',
                'current' => isset($summary['total_seen']) ? (int) $summary['total_seen'] : 0,
                'total' => isset($summary['total_seen']) ? (int) $summary['total_seen'] : 0,
                'message' => '',
            ]
        );

        if ($mode === 'full') {
            $state['last_full_started_at'] = $started_at;
            $state['last_full_finished_at'] = $finished_at;
            $state['last_full_status'] = 'success';
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $current_state
     */
    private function update_progress(array &$current_state, array $changes): void
    {
        $progress = isset($current_state['progress']) && is_array($current_state['progress'])
            ? $this->normalize_progress($current_state['progress'])
            : $this->get_default_progress();

        $current_state['progress'] = array_merge($progress, $changes);
        $this->persist_state($current_state);
    }

    /**
     * @param array<string, mixed> $state
     * @return WP_Error
     */
    private function fail_sync(int $started_at, array $state, WP_Error $error, string $mode): WP_Error
    {
        $state['last_started_at'] = $started_at;
        $state['last_finished_at'] = time();
        $state['last_status'] = 'error';
        $state['last_error'] = $error->get_error_message();
        $state['last_sync_mode'] = $mode;
        $state['summary'] = isset($state['summary']) && is_array($state['summary'])
            ? $this->normalize_summary($state['summary'])
            : $this->normalize_summary([]);
        $state['field_keys'] = isset($state['field_keys']) && is_array($state['field_keys'])
            ? $this->normalize_field_keys($state['field_keys'])
            : [];
        $state['amenity_labels'] = isset($state['amenity_labels']) && is_array($state['amenity_labels'])
            ? $this->normalize_amenity_labels($state['amenity_labels'])
            : [];
        $state['amenity_types'] = isset($state['amenity_types']) && is_array($state['amenity_types'])
            ? $this->normalize_amenity_types($state['amenity_types'])
            : [];
        $state['progress'] = array_merge(
            $this->get_default_progress(),
            [
                'active' => false,
                'mode' => $mode,
                'stage' => 'error',
                'message' => $error->get_error_message(),
            ]
        );

        if ($mode === 'full') {
            $state['last_full_started_at'] = $started_at;
            $state['last_full_status'] = 'error';
        }

        $this->persist_state($state);

        return $error;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function get_existing_post_map(): array
    {
        $posts = get_posts(
            [
                'post_type' => Property_Post_Type::POST_TYPE,
                'post_status' => ['publish', 'draft', 'private', 'pending'],
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]
        );

        $map = [];

        foreach ($posts as $post_id) {
            $imported = (string) get_post_meta((int) $post_id, '_be_property_imported', true);
            if ($imported !== '1') {
                continue;
            }

            $property_id = (string) get_post_meta((int) $post_id, '_be_property_id', true);
            if ($property_id === '') {
                continue;
            }

            $post = get_post((int) $post_id);
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $map[$property_id] = [
                'post_id' => (int) $post_id,
                'source_hash' => (string) get_post_meta((int) $post_id, '_be_property_source_hash', true),
                'import_status' => (string) get_post_meta((int) $post_id, '_be_property_import_status', true),
                'post_status' => $post->post_status,
                'title' => $post->post_title,
            ];
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $property
     * @return array<string, int|string>|WP_Error
     */
    private function create_property_post(
        array $property,
        int $timestamp,
        array $amenity_labels,
        array $amenity_types
    ): array|WP_Error {
        $post_id = wp_insert_post(
            [
                'post_type' => Property_Post_Type::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => $this->sanitize_title_value($property['title'] ?? ''),
            ],
            true
        );

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        $this->persist_property_meta((int) $post_id, $property, $timestamp, 'active', $amenity_labels, $amenity_types);

        return [
            'result' => 'created',
            'post_id' => (int) $post_id,
        ];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $property
     * @return array<string, int|string>|WP_Error
     */
    private function update_property_post(
        int $post_id,
        array $existing,
        array $property,
        int $timestamp,
        array $amenity_labels,
        array $amenity_types
    ): array|WP_Error {
        $next_title = $this->sanitize_title_value($property['title'] ?? '');
        $next_hash = isset($property['source_hash']) && is_string($property['source_hash']) ? $property['source_hash'] : '';
        $force_update = !empty($property['force_update']);
        $hash_changed = $next_hash !== '' && $next_hash !== (string) ($existing['source_hash'] ?? '');
        $status_changed = ($existing['post_status'] ?? '') !== 'publish';
        $import_status_changed = ($existing['import_status'] ?? '') !== 'active';
        $title_changed = $next_title !== (string) ($existing['title'] ?? '');

        if (!$force_update && !$hash_changed && !$status_changed && !$import_status_changed && !$title_changed) {
            update_post_meta($post_id, '_be_property_last_synced_at', $timestamp);

            return [
                'result' => 'unchanged',
                'post_id' => $post_id,
            ];
        }

        $update = [
            'ID' => $post_id,
            'post_status' => 'publish',
        ];

        if ($title_changed || $force_update) {
            $update['post_title'] = $next_title;
        }

        $updated = wp_update_post($update, true);
        if (is_wp_error($updated)) {
            return $updated;
        }

        if ($force_update || $hash_changed) {
            $this->persist_property_meta($post_id, $property, $timestamp, 'active', $amenity_labels, $amenity_types);
        } else {
            update_post_meta($post_id, '_be_property_import_status', 'active');
            update_post_meta($post_id, '_be_property_last_synced_at', $timestamp);
        }

        return [
            'result' => $force_update || $hash_changed || $status_changed || $title_changed ? 'updated' : 'unchanged',
            'post_id' => $post_id,
        ];
    }

    /**
     * @param array<string, mixed> $property
     */
    private function persist_property_meta(
        int $post_id,
        array $property,
        int $timestamp,
        string $import_status,
        array $amenity_labels,
        array $amenity_types
    ): void {
        $storage = $this->build_storage_payload($post_id, $property, $amenity_labels, $amenity_types);
        $property_id = isset($property['property_id']) && is_string($property['property_id']) ? trim($property['property_id']) : '';
        $keyboard_id = isset($property['keyboard_id']) && is_string($property['keyboard_id']) ? trim($property['keyboard_id']) : '';
        $merge_existing_fields = !empty($property['merge_existing_fields']);
        $raw_xml = isset($property['raw_xml']) && is_string($property['raw_xml']) ? $property['raw_xml'] : '';
        $images = isset($property['images']) && is_array($property['images']) ? array_values($property['images']) : [];
        $images_raw_xml = isset($property['images_raw_xml']) && is_string($property['images_raw_xml']) ? $property['images_raw_xml'] : '';
        $source_hash = isset($property['source_hash']) && is_string($property['source_hash'])
            ? $property['source_hash']
            : $this->build_property_source_hash($raw_xml, $images_raw_xml);

        if ($merge_existing_fields) {
            if ($property_id === '') {
                $property_id = (string) get_post_meta($post_id, '_be_property_id', true);
            }

            if ($keyboard_id === '') {
                $keyboard_id = (string) get_post_meta($post_id, '_be_property_keyboardid', true);
            }
        }

        if (!empty($property['preserve_existing_source_hash'])) {
            $existing_source_hash = (string) get_post_meta($post_id, '_be_property_source_hash', true);
            if ($existing_source_hash !== '') {
                $source_hash = $existing_source_hash;
            }
        }

        update_post_meta($post_id, '_be_property_id', $property_id);
        update_post_meta($post_id, '_be_property_keyboardid', $keyboard_id);
        update_post_meta($post_id, '_be_property_fields', $storage['fields']);
        update_post_meta($post_id, '_be_property_field_order', $storage['field_order']);
        $this->persist_queryable_field_meta($post_id, $storage['raw_fields'], !empty($property['preserve_existing_queryable_meta']));
        update_post_meta($post_id, '_be_property_images', $images);
        update_post_meta($post_id, '_be_property_images_raw_xml', $images_raw_xml);
        update_post_meta($post_id, '_be_property_raw_xml', $raw_xml);
        update_post_meta($post_id, '_be_property_last_synced_at', $timestamp);
        update_post_meta($post_id, '_be_property_source_hash', $source_hash);
        update_post_meta($post_id, '_be_property_import_status', $import_status);
        update_post_meta($post_id, '_be_property_imported', '1');
    }

    /**
     * @param array<string, mixed> $property
     * @param array<string, string> $amenity_labels
     * @param array<string, string> $amenity_types
     * @return array{fields: array<string, mixed>, field_order: array<int, string>, raw_fields: array<string, mixed>}
     */
    private function build_storage_payload(int $post_id, array $property, array $amenity_labels, array $amenity_types): array
    {
        $raw_fields = isset($property['fields']) && is_array($property['fields']) ? $property['fields'] : [];
        $normalized_amenities = isset($property['normalized_amenities']) && is_array($property['normalized_amenities'])
            ? $this->normalize_prebuilt_amenities($property['normalized_amenities'])
            : null;

        if ($normalized_amenities !== null) {
            $normalized_fields = $raw_fields;
            $normalized_fields['amenities'] = $normalized_amenities;
        } else {
            $normalized = $this->normalize_property_fields($raw_fields, $amenity_labels, $amenity_types);
            $normalized_fields = $normalized['fields'];
        }

        if (!empty($property['merge_existing_fields'])) {
            $normalized_fields = $this->merge_existing_property_fields($post_id, $normalized_fields);
        }

        return [
            'fields' => $normalized_fields,
            'field_order' => self::CURATED_FIELD_ORDER,
            'raw_fields' => $raw_fields,
        ];
    }

    /**
     * @param array<string, mixed> $new_fields
     * @return array<string, mixed>
     */
    private function merge_existing_property_fields(int $post_id, array $new_fields): array
    {
        $existing_fields = get_post_meta($post_id, '_be_property_fields', true);
        if (!is_array($existing_fields)) {
            return $new_fields;
        }

        $existing_amenities = isset($existing_fields['amenities']) && is_array($existing_fields['amenities'])
            ? $existing_fields['amenities']
            : [];
        $next_amenities = array_key_exists('amenities', $new_fields)
            ? (is_array($new_fields['amenities']) ? $new_fields['amenities'] : [])
            : $existing_amenities;

        unset($existing_fields['amenities'], $new_fields['amenities']);

        $merged = array_merge($existing_fields, $new_fields);
        $merged['amenities'] = $next_amenities;

        return $merged;
    }

    /**
     * @param array<int, array<string, mixed>> $amenities
     * @return array<int, array<string, string>>
     */
    private function normalize_prebuilt_amenities(array $amenities): array
    {
        $normalized = [];

        foreach ($amenities as $amenity) {
            if (!is_array($amenity)) {
                continue;
            }

            $label = isset($amenity['label']) && is_scalar($amenity['label']) ? trim((string) $amenity['label']) : '';
            if ($label === '') {
                continue;
            }

            $value = isset($amenity['value']) && is_scalar($amenity['value']) ? trim((string) $amenity['value']) : '-1';
            $key = isset($amenity['key']) && is_scalar($amenity['key']) ? trim((string) $amenity['key']) : sanitize_title($label);
            $type = isset($amenity['type']) && is_scalar($amenity['type']) ? trim((string) $amenity['type']) : '';

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'value' => $value,
                'type' => $type,
                'display' => $label,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $raw_fields
     */
    private function persist_queryable_field_meta(int $post_id, array $raw_fields, bool $preserve_existing = false): void
    {
        $next_meta_keys = [];

        foreach ($raw_fields as $field_key => $value) {
            if (!is_string($field_key)) {
                continue;
            }

            $meta_key = $this->build_queryable_meta_key($field_key);
            if ($meta_key === '') {
                continue;
            }

            update_post_meta($post_id, $meta_key, $this->normalize_queryable_meta_value($field_key, $value));
            $next_meta_keys[] = $meta_key;
        }

        $next_meta_keys = array_values(array_unique($next_meta_keys));
        $previous_meta_keys = get_post_meta($post_id, self::QUERYABLE_META_INDEX_KEY, true);
        if (!is_array($previous_meta_keys)) {
            $previous_meta_keys = [];
        }

        if (!$preserve_existing) {
            foreach ($previous_meta_keys as $meta_key) {
                if (!is_string($meta_key) || in_array($meta_key, $next_meta_keys, true)) {
                    continue;
                }

                delete_post_meta($post_id, $meta_key);
            }

            update_post_meta($post_id, self::QUERYABLE_META_INDEX_KEY, $next_meta_keys);

            return;
        }

        update_post_meta(
            $post_id,
            self::QUERYABLE_META_INDEX_KEY,
            array_values(array_unique(array_merge($previous_meta_keys, $next_meta_keys)))
        );
    }

    private function build_queryable_meta_key(string $field_key): string
    {
        $normalized_key = trim($field_key);
        if ($normalized_key === '') {
            return '';
        }

        return self::QUERYABLE_META_PREFIX . $normalized_key;
    }

    private function normalize_queryable_meta_value(string $field_key, mixed $value): string
    {
        $normalized_value = $this->normalize_property_field_value($value);

        if ($this->is_amenity_key($field_key)) {
            return ($normalized_value !== '' && $normalized_value !== '0') ? '1' : '0';
        }

        return $normalized_value;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function mark_property_missing(int $post_id, array $record): bool
    {
        $already_missing = ($record['import_status'] ?? '') === 'missing' && ($record['post_status'] ?? '') === 'draft';
        if ($already_missing) {
            return false;
        }

        $updated = wp_update_post(
            [
                'ID' => $post_id,
                'post_status' => 'draft',
            ],
            true
        );

        if (is_wp_error($updated)) {
            return false;
        }

        update_post_meta($post_id, '_be_property_import_status', 'missing');
        $this->property_taxonomies->clear_terms_for_property($post_id);

        return true;
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>
     */
    private function normalize_summary(array $summary): array
    {
        return [
            'created' => isset($summary['created']) ? (int) $summary['created'] : 0,
            'updated' => isset($summary['updated']) ? (int) $summary['updated'] : 0,
            'deactivated' => isset($summary['deactivated']) ? (int) $summary['deactivated'] : 0,
            'unchanged' => isset($summary['unchanged']) ? (int) $summary['unchanged'] : 0,
            'skipped' => isset($summary['skipped']) ? (int) $summary['skipped'] : 0,
            'total_seen' => isset($summary['total_seen']) ? (int) $summary['total_seen'] : 0,
            'started_at' => isset($summary['started_at']) ? (int) $summary['started_at'] : 0,
            'finished_at' => isset($summary['finished_at']) ? (int) $summary['finished_at'] : 0,
            'skipped_items' => isset($summary['skipped_items']) && is_array($summary['skipped_items'])
                ? array_values($summary['skipped_items'])
                : [],
        ];
    }

    /**
     * @param array<int, mixed> $field_keys
     * @return array<int, string>
     */
    private function normalize_field_keys(array $field_keys): array
    {
        $normalized = [];

        foreach ($field_keys as $key) {
            if (!is_scalar($key)) {
                continue;
            }

            $value = trim((string) $key);
            if ($value === '' || in_array($value, $normalized, true)) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $labels
     * @return array<string, string>
     */
    private function normalize_amenity_labels(array $labels): array
    {
        $normalized = [];

        foreach ($labels as $key => $label) {
            if (!is_string($key) || !is_scalar($label)) {
                continue;
            }

            $sanitized_key = trim($key);
            $sanitized_label = trim((string) $label);
            if ($sanitized_key === '' || $sanitized_label === '') {
                continue;
            }

            $normalized[$sanitized_key] = $sanitized_label;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $types
     * @return array<string, string>
     */
    private function normalize_amenity_types(array $types): array
    {
        $normalized = [];

        foreach ($types as $key => $type) {
            if (!is_string($key) || !is_scalar($type)) {
                continue;
            }

            $sanitized_key = trim($key);
            $sanitized_type = trim((string) $type);
            if ($sanitized_key === '' || $sanitized_type === '') {
                continue;
            }

            $normalized[$sanitized_key] = $sanitized_type;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $progress
     * @return array<string, mixed>
     */
    private function normalize_progress(array $progress): array
    {
        return [
            'active' => !empty($progress['active']),
            'mode' => isset($progress['mode']) && is_string($progress['mode']) ? $progress['mode'] : 'none',
            'stage' => isset($progress['stage']) && is_string($progress['stage']) ? $progress['stage'] : 'idle',
            'current' => isset($progress['current']) ? max(0, (int) $progress['current']) : 0,
            'total' => isset($progress['total']) ? max(0, (int) $progress['total']) : 0,
            'message' => isset($progress['message']) && is_string($progress['message']) ? $progress['message'] : '',
            'current_property_id' => isset($progress['current_property_id']) && is_string($progress['current_property_id'])
                ? $progress['current_property_id']
                : '',
            'current_property_title' => isset($progress['current_property_title']) && is_string($progress['current_property_title'])
                ? $progress['current_property_title']
                : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function get_default_state(): array
    {
        return [
            'last_started_at' => 0,
            'last_finished_at' => 0,
            'last_status' => 'idle',
            'last_error' => '',
            'last_sync_mode' => 'none',
            'last_full_started_at' => 0,
            'last_full_finished_at' => 0,
            'last_full_status' => 'idle',
            'summary' => $this->normalize_summary([]),
            'field_keys' => [],
            'amenity_labels' => [],
            'amenity_types' => [],
            'progress' => $this->get_default_progress(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function get_default_progress(): array
    {
        return [
            'active' => false,
            'mode' => 'none',
            'stage' => 'idle',
            'current' => 0,
            'total' => 0,
            'message' => '',
            'current_property_id' => '',
            'current_property_title' => '',
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function persist_state(array $state): void
    {
        update_option(self::SYNC_STATE_OPTION_KEY, $state, false);
    }

    private function format_timestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return __('Not available', 'barefoot-engine');
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    private function format_last_access_time(int $timestamp): string
    {
        return wp_date('Y-m-d H:i:s', $timestamp);
    }

    private function sanitize_title_value(mixed $value): string
    {
        $title = is_scalar($value) ? trim((string) $value) : '';
        if ($title === '') {
            return __('Property', 'barefoot-engine');
        }

        return wp_strip_all_tags($title);
    }

    private function build_progress_message(int $current, int $total, string $property_id = '', mixed $title = ''): string
    {
        $message = sprintf(
            /* translators: 1: current property count, 2: total property count */
            __('Syncing property %1$d of %2$d…', 'barefoot-engine'),
            $current,
            $total
        );

        $normalized_title = is_scalar($title) ? trim((string) $title) : '';
        if ($normalized_title !== '') {
            return sprintf(
                /* translators: 1: progress summary, 2: property title, 3: property id */
                __('%1$s %2$s (%3$s)', 'barefoot-engine'),
                $message,
                $normalized_title,
                $property_id
            );
        }

        if ($property_id !== '') {
            return sprintf(
                /* translators: 1: progress summary, 2: property id */
                __('%1$s %2$s', 'barefoot-engine'),
                $message,
                $property_id
            );
        }

        return $message;
    }

    private function build_property_source_hash(string $raw_xml, string $images_raw_xml): string
    {
        return sha1($raw_xml . "\n--images--\n" . $images_raw_xml);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, string> $amenity_labels
     * @param array<string, string> $amenity_types
     * @return array{fields: array<string, mixed>, field_order: array<int, string>}
     */
    private function normalize_property_fields(array $fields, array $amenity_labels, array $amenity_types): array
    {
        $normalized_fields = [];
        $amenities = [];

        foreach ($fields as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if ($this->is_amenity_key($key)) {
                $amenity = $this->build_amenity_entry($key, $value, $amenity_labels, $amenity_types);
                if ($amenity !== null) {
                    $amenities[] = $amenity;
                }

                continue;
            }

            $normalized_fields[$key] = $value;
        }

        $normalized_fields['amenities'] = $amenities;

        return [
            'fields' => $normalized_fields,
            'field_order' => self::CURATED_FIELD_ORDER,
        ];
    }

    /**
     * @param array<string, string> $amenity_labels
     * @param array<string, string> $amenity_types
     * @return array<string, string>|null
     */
    private function build_amenity_entry(string $key, mixed $value, array $amenity_labels, array $amenity_types): ?array
    {
        $normalized_value = $this->normalize_property_field_value($value);
        $amenity_type = $this->resolve_amenity_type($key, $amenity_types);
        if (!$this->should_include_amenity_value($normalized_value, $amenity_type)) {
            return null;
        }

        $label = $this->resolve_property_field_label($key, $amenity_labels);

        return [
            'key' => $key,
            'label' => $label,
            'value' => $normalized_value,
            'type' => $amenity_type,
            'display' => $this->build_amenity_display($label),
        ];
    }

    private function is_amenity_key(string $key): bool
    {
        if (preg_match('/^a(\d+)$/i', $key, $matches) !== 1) {
            return false;
        }

        $number = (int) $matches[1];

        return $number >= 75 && $number <= 258;
    }

    private function normalize_property_field_value(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    private function should_include_amenity_value(string $value, string $amenity_type): bool
    {
        if ($value === '') {
            return false;
        }

        $normalized = strtolower($value);
        if ($amenity_type === 'check box' || $amenity_type === 'radio button') {
            return $this->is_checked_amenity_value($normalized);
        }

        if ($amenity_type === 'list box') {
            return !in_array($normalized, ['0', '-1'], true);
        }

        if ($amenity_type === 'text box') {
            return true;
        }

        return !in_array($normalized, ['0', 'false', 'no', 'n'], true);
    }

    private function build_amenity_display(string $label): string
    {
        return $label;
    }

    private function is_checked_amenity_value(string $value): bool
    {
        return in_array(strtolower($value), ['-1', '1', 'true', 'yes', 'y'], true);
    }

    /**
     * @param array<string, string> $amenity_types
     */
    private function resolve_amenity_type(string $key, array $amenity_types): string
    {
        if (!isset($amenity_types[$key]) || !is_string($amenity_types[$key])) {
            return '';
        }

        return strtolower(trim($amenity_types[$key]));
    }

    /**
     * @param array<string, string> $amenity_labels
     */
    private function resolve_property_field_label(string $key, array $amenity_labels): string
    {
        $normalized_key = trim($key);
        if ($normalized_key === '') {
            return '';
        }

        if (isset($amenity_labels[$normalized_key]) && is_string($amenity_labels[$normalized_key])) {
            $amenity_label = trim($amenity_labels[$normalized_key]);
            if ($amenity_label !== '') {
                return $amenity_label;
            }
        }

        return $this->humanize_key($normalized_key);
    }

    private function humanize_key(string $key): string
    {
        if (preg_match('/^a\d+$/', $key) === 1) {
            return $key;
        }

        $lower = strtolower($key);
        $special_cases = [
            'propertyid' => 'Property ID',
            'keyboardid' => 'Keyboard ID',
            'addressid' => 'Address ID',
            'propaddressnew' => 'Prop Address New',
            'propaddress' => 'Prop Address',
            'mindays' => 'Min Days',
            'minprice' => 'Min Price',
            'maxprice' => 'Max Price',
            'imagepath' => 'Image Path',
            'extdescription' => 'Ext Description',
            'unitcomments' => 'Unit Comments',
            'internetdescription' => 'Internet Description',
            'videolink' => 'Video Link',
        ];

        if (isset($special_cases[$lower])) {
            return $special_cases[$lower];
        }

        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        if (!is_string($label)) {
            return $key;
        }

        $label = str_replace(['_', '-'], ' ', $label);
        $label = preg_replace('/\s+/', ' ', trim($label));

        if (!is_string($label) || $label === '') {
            return $key;
        }

        return ucwords($label);
    }
}
