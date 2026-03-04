<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Api_Integration_Settings;
use BarefootEngine\Services\Barefoot_Api_Client;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Availability_Service
{
    public const AVAILABILITY_STATE_OPTION_KEY = 'barefoot_engine_availability_state';
    private const TRANSIENT_KEY_PREFIX = 'barefoot_engine_availability_';
    private const DEFAULT_CACHE_TTL = 900;
    private const DEFAULT_PROBE_TTL = 300;
    private const DEFAULT_MAX_NIGHTS = 31;

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
     * @return array<int, string>|null
     */
    public function get_cached_available_property_ids(string $check_in, string $check_out): ?array
    {
        if (!$this->has_valid_date_range($check_in, $check_out)) {
            return null;
        }

        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return null;
        }

        $this->maybe_refresh_cache_version($settings);

        $cache_entry = $this->get_query_cache_entry($settings, $check_in, $check_out);
        if (!is_array($cache_entry)) {
            return null;
        }

        $property_ids = isset($cache_entry['available_property_ids']) && is_array($cache_entry['available_property_ids'])
            ? $cache_entry['available_property_ids']
            : [];

        return array_values(
            array_filter(
                array_map(
                    static fn($property_id): string => is_scalar($property_id) ? trim((string) $property_id) : '',
                    $property_ids
                ),
                static fn(string $property_id): bool => $property_id !== ''
            )
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function search_available_property_ids(string $check_in, string $check_out, bool $force_refresh = false): array|WP_Error
    {
        if (!$this->has_valid_date_range($check_in, $check_out)) {
            return new WP_Error(
                'barefoot_engine_availability_invalid_range',
                __('A valid availability search range is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_availability_missing_credentials',
                __('Please save your Barefoot API credentials before searching availability.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $this->maybe_refresh_cache_version($settings);

        $state = $this->load_availability_state();
        $cache_version = isset($state['cache_version']) ? (int) $state['cache_version'] : 1;
        $cache_entry = $force_refresh ? null : $this->get_query_cache_entry($settings, $check_in, $check_out);

        if (is_array($cache_entry)) {
            return [
                'check_in' => $check_in,
                'check_out' => $check_out,
                'available_property_ids' => isset($cache_entry['available_property_ids']) && is_array($cache_entry['available_property_ids'])
                    ? array_values($cache_entry['available_property_ids'])
                    : [],
                'cache' => [
                    'hit' => true,
                    'version' => $cache_version,
                    'fetched_at' => isset($cache_entry['fetched_at']) ? (int) $cache_entry['fetched_at'] : 0,
                ],
                'status' => [
                    'used_live_api' => false,
                    'used_cached_result' => true,
                ],
            ];
        }

        $live_result = $this->fetch_live_available_properties($settings, $check_in, $check_out);
        if (is_wp_error($live_result)) {
            return $live_result;
        }

        $cache_entry = [
            'check_in' => $check_in,
            'check_out' => $check_out,
            'weekly' => 0,
            'available_property_ids' => $live_result['property_ids'],
            'properties' => $live_result['properties'],
            'fetched_at' => time(),
            'source' => 'live',
        ];

        $this->set_query_cache_entry($settings, $check_in, $check_out, $cache_entry);

        return [
            'check_in' => $check_in,
            'check_out' => $check_out,
            'available_property_ids' => $live_result['property_ids'],
            'cache' => [
                'hit' => false,
                'version' => $cache_version,
                'fetched_at' => (int) $cache_entry['fetched_at'],
            ],
            'status' => [
                'used_live_api' => true,
                'used_cached_result' => false,
            ],
        ];
    }

    public function has_valid_date_range(string $check_in, string $check_out): bool
    {
        $normalized_check_in = trim($check_in);
        $normalized_check_out = trim($check_out);

        if (!$this->is_valid_ymd_date($normalized_check_in) || !$this->is_valid_ymd_date($normalized_check_out)) {
            return false;
        }

        if ($normalized_check_out <= $normalized_check_in) {
            return false;
        }

        $check_in_date = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized_check_in, wp_timezone());
        $check_out_date = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized_check_out, wp_timezone());

        if (!$check_in_date instanceof \DateTimeImmutable || !$check_out_date instanceof \DateTimeImmutable) {
            return false;
        }

        $nights = (int) $check_in_date->diff($check_out_date)->days;

        return $nights > 0 && $nights <= $this->get_max_nights();
    }

    /**
     * @param array<string, array<string, string>> $settings
     */
    private function maybe_refresh_cache_version(array $settings): void
    {
        $state = $this->load_availability_state();
        $now = time();
        $probe_ttl = $this->get_probe_ttl();
        $last_probe_at = isset($state['last_probe_at']) ? (int) $state['last_probe_at'] : 0;

        if ($last_probe_at > 0 && ($now - $last_probe_at) < $probe_ttl) {
            return;
        }

        $last_access = isset($state['last_access']) && is_string($state['last_access'])
            ? trim($state['last_access'])
            : '';

        if ($last_access === '') {
            $state['last_probe_at'] = $now;
            $state['last_access'] = $this->format_last_access_time($now);
            $state['last_status'] = 'success';
            $state['last_error'] = '';
            $this->save_availability_state($state);

            return;
        }

        $response = $this->api_client->fetch_last_avail_changed_properties_string($settings, $last_access);
        if (is_wp_error($response)) {
            $state['last_probe_at'] = $now;
            $state['last_status'] = 'error';
            $state['last_error'] = $response->get_error_message();
            $this->save_availability_state($state);

            return;
        }

        $parsed = $this->parser->parse_last_avail_changed_properties($response);
        if (is_wp_error($parsed)) {
            $state['last_probe_at'] = $now;
            $state['last_status'] = 'error';
            $state['last_error'] = $parsed->get_error_message();
            $this->save_availability_state($state);

            return;
        }

        $changed_property_ids = isset($parsed['property_ids']) && is_array($parsed['property_ids'])
            ? array_values($parsed['property_ids'])
            : [];

        if ($changed_property_ids !== []) {
            $state['cache_version'] = max(1, (int) ($state['cache_version'] ?? 1)) + 1;
        }

        $state['last_probe_at'] = $now;
        $state['last_access'] = $this->format_last_access_time($now);
        $state['last_changed_ids'] = $changed_property_ids;
        $state['last_status'] = 'success';
        $state['last_error'] = '';

        $this->save_availability_state($state);
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return array<string, mixed>|WP_Error
     */
    private function fetch_live_available_properties(array $settings, string $check_in, string $check_out): array|WP_Error
    {
        $response = $this->api_client->fetch_property_availability_by_date_xml($settings, $check_in, $check_out, 0);
        if (is_wp_error($response)) {
            return $response;
        }

        $parsed = $this->parser->parse_property_availability_by_date($response);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        $properties = isset($parsed['properties']) && is_array($parsed['properties']) ? $parsed['properties'] : [];
        $property_ids = isset($parsed['property_ids']) && is_array($parsed['property_ids']) ? $parsed['property_ids'] : [];

        return [
            'properties' => $properties,
            'property_ids' => array_values($property_ids),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function load_availability_state(): array
    {
        $stored = get_option(self::AVAILABILITY_STATE_OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge(
            [
                'cache_version' => 1,
                'last_probe_at' => 0,
                'last_access' => '',
                'last_changed_ids' => [],
                'last_status' => 'idle',
                'last_error' => '',
            ],
            $stored
        );
    }

    /**
     * @param array<string, mixed> $state
     */
    private function save_availability_state(array $state): void
    {
        update_option(self::AVAILABILITY_STATE_OPTION_KEY, $state, false);
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return array<string, mixed>|null
     */
    private function get_query_cache_entry(array $settings, string $check_in, string $check_out): ?array
    {
        $transient_key = $this->build_query_cache_key($settings, $check_in, $check_out);
        $cached = get_transient($transient_key);

        return is_array($cached) ? $cached : null;
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @param array<string, mixed> $cache_entry
     */
    private function set_query_cache_entry(array $settings, string $check_in, string $check_out, array $cache_entry): void
    {
        $transient_key = $this->build_query_cache_key($settings, $check_in, $check_out);
        set_transient($transient_key, $cache_entry, $this->get_cache_ttl());
    }

    /**
     * @param array<string, array<string, string>> $settings
     */
    private function build_query_cache_key(array $settings, string $check_in, string $check_out): string
    {
        $state = $this->load_availability_state();
        $cache_version = max(1, (int) ($state['cache_version'] ?? 1));
        $api = isset($settings['api']) && is_array($settings['api']) ? $settings['api'] : [];
        $barefoot_account = isset($api['company_id']) && is_string($api['company_id']) ? trim($api['company_id']) : '';

        $hash = md5(
            wp_json_encode(
                [
                    'barefootAccount' => $barefoot_account,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'weekly' => 0,
                ]
            ) ?: ''
        );

        return self::TRANSIENT_KEY_PREFIX . $cache_version . '_' . $hash;
    }

    private function get_cache_ttl(): int
    {
        return max(
            60,
            (int) apply_filters('barefoot_engine_availability_cache_ttl', self::DEFAULT_CACHE_TTL)
        );
    }

    private function get_probe_ttl(): int
    {
        return max(
            60,
            (int) apply_filters('barefoot_engine_availability_probe_ttl', self::DEFAULT_PROBE_TTL)
        );
    }

    private function get_max_nights(): int
    {
        return max(
            1,
            (int) apply_filters('barefoot_engine_availability_max_nights', self::DEFAULT_MAX_NIGHTS)
        );
    }

    private function is_valid_ymd_date(string $value): bool
    {
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        if (!$date instanceof \DateTimeImmutable) {
            return false;
        }

        return $date->format('Y-m-d') === $value;
    }

    private function format_last_access_time(int $timestamp): string
    {
        return wp_date('Y-m-d H:i:s', $timestamp, wp_timezone());
    }
}
