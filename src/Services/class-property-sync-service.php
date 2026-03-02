<?php

namespace BarefootEngine\Services;

use BarefootEngine\Properties\Property_Parser;
use BarefootEngine\Properties\Property_Post_Type;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Sync_Service
{
    public const SYNC_STATE_OPTION_KEY = 'barefoot_engine_property_sync_state';

    private Barefoot_Api_Client $api_client;
    private Api_Integration_Settings $api_settings;
    private Property_Parser $parser;

    public function __construct(
        ?Barefoot_Api_Client $api_client = null,
        ?Api_Integration_Settings $api_settings = null,
        ?Property_Parser $parser = null
    ) {
        $this->api_client = $api_client ?? new Barefoot_Api_Client();
        $this->api_settings = $api_settings ?? new Api_Integration_Settings();
        $this->parser = $parser ?? new Property_Parser();
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
        $state['last_error'] = isset($state['last_error']) && is_string($state['last_error']) ? $state['last_error'] : '';
        $state['last_status'] = isset($state['last_status']) && is_string($state['last_status']) ? $state['last_status'] : 'idle';
        $state['last_started_at'] = isset($state['last_started_at']) ? (int) $state['last_started_at'] : 0;
        $state['last_finished_at'] = isset($state['last_finished_at']) ? (int) $state['last_finished_at'] : 0;
        $state['last_started_human'] = $this->format_timestamp($state['last_started_at']);
        $state['last_finished_human'] = $this->format_timestamp($state['last_finished_at']);

        return $state;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function sync(): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_property_missing_credentials',
                __('Please save your Barefoot API credentials before syncing properties.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $state = $this->get_sync_state();
        $started_at = time();

        $this->persist_state(
            [
                'last_started_at' => $started_at,
                'last_status' => 'running',
                'last_error' => '',
            ] + $state
        );

        $amenity_labels = $this->api_client->fetch_amenity_labels($settings);
        if (is_wp_error($amenity_labels)) {
            return $this->fail_sync($started_at, $state, $amenity_labels);
        }

        $property_xml = $this->api_client->fetch_property_ext_xml($settings);
        if (is_wp_error($property_xml)) {
            return $this->fail_sync($started_at, $state, $property_xml);
        }

        $parsed = $this->parser->parse_property_list($property_xml);
        if (is_wp_error($parsed)) {
            return $this->fail_sync($started_at, $state, $parsed);
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
            $result = $existing_record === null
                ? $this->create_property_post($property, $started_at)
                : $this->update_property_post((int) $existing_record['post_id'], $existing_record, $property, $started_at);

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

        $final_state = [
            'last_started_at' => $started_at,
            'last_finished_at' => $summary['finished_at'],
            'last_status' => 'success',
            'last_error' => '',
            'summary' => $summary,
            'field_keys' => $field_keys,
            'amenity_labels' => $amenity_labels,
        ];

        $this->persist_state($final_state);

        return [
            'summary' => $summary,
            'sync_state' => $this->get_sync_state(),
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
            'summary' => $this->normalize_summary([]),
            'field_keys' => [],
            'amenity_labels' => [],
        ];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function persist_state(array $state): void
    {
        update_option(self::SYNC_STATE_OPTION_KEY, $state, false);
    }

    /**
     * @param array<string, mixed> $previous_state
     * @return WP_Error
     */
    private function fail_sync(int $started_at, array $previous_state, WP_Error $error): WP_Error
    {
        $failed_state = [
            'last_started_at' => $started_at,
            'last_finished_at' => time(),
            'last_status' => 'error',
            'last_error' => $error->get_error_message(),
            'summary' => isset($previous_state['summary']) && is_array($previous_state['summary'])
                ? $previous_state['summary']
                : $this->normalize_summary([]),
            'field_keys' => isset($previous_state['field_keys']) && is_array($previous_state['field_keys'])
                ? $previous_state['field_keys']
                : [],
            'amenity_labels' => isset($previous_state['amenity_labels']) && is_array($previous_state['amenity_labels'])
                ? $previous_state['amenity_labels']
                : [],
        ];

        $this->persist_state($failed_state);

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
     * @return array<string, string>|WP_Error
     */
    private function create_property_post(array $property, int $timestamp): array|WP_Error
    {
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

        $this->persist_property_meta((int) $post_id, $property, $timestamp, 'active');

        return [
            'result' => 'created',
        ];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $property
     * @return array<string, string>|WP_Error
     */
    private function update_property_post(int $post_id, array $existing, array $property, int $timestamp): array|WP_Error
    {
        $next_title = $this->sanitize_title_value($property['title'] ?? '');
        $next_hash = isset($property['source_hash']) && is_string($property['source_hash']) ? $property['source_hash'] : '';
        $hash_changed = $next_hash !== '' && $next_hash !== (string) ($existing['source_hash'] ?? '');
        $status_changed = ($existing['post_status'] ?? '') !== 'publish';
        $import_status_changed = ($existing['import_status'] ?? '') !== 'active';
        $title_changed = $next_title !== (string) ($existing['title'] ?? '');

        if (!$hash_changed && !$status_changed && !$import_status_changed && !$title_changed) {
            update_post_meta($post_id, '_be_property_last_synced_at', $timestamp);

            return [
                'result' => 'unchanged',
            ];
        }

        $update = [
            'ID' => $post_id,
            'post_status' => 'publish',
        ];

        if ($title_changed) {
            $update['post_title'] = $next_title;
        }

        $updated = wp_update_post($update, true);
        if (is_wp_error($updated)) {
            return $updated;
        }

        if ($hash_changed) {
            $this->persist_property_meta($post_id, $property, $timestamp, 'active');
        } else {
            update_post_meta($post_id, '_be_property_import_status', 'active');
            update_post_meta($post_id, '_be_property_last_synced_at', $timestamp);
        }

        return [
            'result' => 'updated',
        ];
    }

    /**
     * @param array<string, mixed> $property
     */
    private function persist_property_meta(int $post_id, array $property, int $timestamp, string $import_status): void
    {
        $property_id = isset($property['property_id']) && is_string($property['property_id']) ? $property['property_id'] : '';
        $keyboard_id = isset($property['keyboard_id']) && is_string($property['keyboard_id']) ? $property['keyboard_id'] : '';
        $fields = isset($property['fields']) && is_array($property['fields']) ? $property['fields'] : [];
        $field_order = isset($property['field_order']) && is_array($property['field_order']) ? $property['field_order'] : array_keys($fields);
        $raw_xml = isset($property['raw_xml']) && is_string($property['raw_xml']) ? $property['raw_xml'] : '';
        $source_hash = isset($property['source_hash']) && is_string($property['source_hash']) ? $property['source_hash'] : sha1($raw_xml);

        update_post_meta($post_id, '_be_property_id', $property_id);
        update_post_meta($post_id, '_be_property_keyboardid', $keyboard_id);
        update_post_meta($post_id, '_be_property_fields', $fields);
        update_post_meta($post_id, '_be_property_field_order', $field_order);
        update_post_meta($post_id, '_be_property_raw_xml', $raw_xml);
        update_post_meta($post_id, '_be_property_last_synced_at', $timestamp);
        update_post_meta($post_id, '_be_property_source_hash', $source_hash);
        update_post_meta($post_id, '_be_property_import_status', $import_status);
        update_post_meta($post_id, '_be_property_imported', '1');
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

    private function format_timestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return __('Not available', 'barefoot-engine');
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    private function sanitize_title_value(mixed $value): string
    {
        $title = is_scalar($value) ? trim((string) $value) : '';
        if ($title === '') {
            return __('Property', 'barefoot-engine');
        }

        return wp_strip_all_tags($title);
    }
}
