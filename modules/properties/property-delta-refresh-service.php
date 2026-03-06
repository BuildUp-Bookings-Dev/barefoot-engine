<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Api_Integration_Settings;
use BarefootEngine\Services\Barefoot_Api_Client;
use BarefootEngine\Services\Property_Sync_Service;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Delta_Refresh_Service
{
    public const OPTION_KEY = 'barefoot_engine_delta_refresh_state';
    public const CRON_HOOK = 'barefoot_engine_delta_refresh_run';
    public const CRON_SCHEDULE = 'barefoot_engine_every_six_hours';
    private const LOCK_TRANSIENT_KEY = 'barefoot_engine_delta_refresh_lock';
    private const DEFAULT_PRECHECK_TTL = 21600;
    private const DEFAULT_LOCK_TTL = 900;
    private const DEFAULT_QUEUE_COOLDOWN = 60;

    private Barefoot_Api_Client $api_client;
    private Api_Integration_Settings $api_settings;
    private Property_Parser $parser;
    private Property_Sync_Service $sync_service;
    private Property_Availability_Service $availability_service;

    public function __construct(
        ?Barefoot_Api_Client $api_client = null,
        ?Api_Integration_Settings $api_settings = null,
        ?Property_Parser $parser = null,
        ?Property_Sync_Service $sync_service = null,
        ?Property_Availability_Service $availability_service = null
    ) {
        $this->api_client = $api_client ?? new Barefoot_Api_Client();
        $this->api_settings = $api_settings ?? new Api_Integration_Settings();
        $this->parser = $parser ?? new Property_Parser();
        $this->sync_service = $sync_service ?? new Property_Sync_Service();
        $this->availability_service = $availability_service ?? new Property_Availability_Service();
    }

    /**
     * @param array<string, array<string, mixed>> $schedules
     * @return array<string, array<string, mixed>>
     */
    public function register_cron_schedule(array $schedules): array
    {
        if (isset($schedules[self::CRON_SCHEDULE])) {
            return $schedules;
        }

        $schedules[self::CRON_SCHEDULE] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Every 6 Hours (Barefoot Delta Refresh)', 'barefoot-engine'),
        ];

        return $schedules;
    }

    public function ensure_scheduled_event(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + 120, self::CRON_SCHEDULE, self::CRON_HOOK);
    }

    public function run_scheduled_event(): void
    {
        $result = $this->run_delta_refresh();
        if (!is_wp_error($result)) {
            return;
        }

        $state = $this->get_state();
        $state['last_status'] = 'error';
        $state['last_error'] = $result->get_error_message();
        $state['last_finished_at'] = time();
        $this->persist_state($state);
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function run_delta_refresh(bool $force = false): array|WP_Error
    {
        if (!$this->acquire_lock()) {
            return new WP_Error(
                'barefoot_engine_delta_refresh_locked',
                __('A delta refresh is already running.', 'barefoot-engine'),
                ['status' => 409]
            );
        }

        $started_at = time();
        $state = $this->get_state();
        $state['last_started_at'] = $started_at;
        $state['last_status'] = 'running';
        $state['last_error'] = '';
        $this->persist_state($state);

        try {
            $settings = $this->api_settings->get_settings();
            if (!$this->api_settings->has_required_credentials($settings)) {
                return $this->finalize_error(
                    $state,
                    new WP_Error(
                        'barefoot_engine_delta_refresh_missing_credentials',
                        __('Please save your Barefoot API credentials before running delta refresh.', 'barefoot-engine'),
                        ['status' => 400]
                    )
                );
            }

            $property_last_access = $this->resolve_property_last_access($state);
            $property_probe = $this->probe_property_changes($property_last_access);
            if (is_wp_error($property_probe)) {
                return $this->finalize_error($state, $property_probe);
            }

            $changes = isset($property_probe['parsed']) && is_array($property_probe['parsed']) ? $property_probe['parsed'] : [];
            $changed_property_ids = isset($changes['all_update_property_ids']) && is_array($changes['all_update_property_ids'])
                ? array_values($changes['all_update_property_ids'])
                : [];
            $cancelled_property_ids = isset($changes['cancelled_property_ids']) && is_array($changes['cancelled_property_ids'])
                ? array_values($changes['cancelled_property_ids'])
                : [];

            $summary = [
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'deactivated' => 0,
                'skipped' => 0,
                'changed_property_ids' => count($changed_property_ids),
                'cancelled_property_ids' => count($cancelled_property_ids),
                'availability_changed_ids' => 0,
                'forced' => $force ? 1 : 0,
            ];

            foreach ($changed_property_ids as $property_id) {
                $result = $this->sync_service->sync_property_by_id($property_id);
                if (is_wp_error($result)) {
                    $summary['skipped']++;
                    continue;
                }

                $result_key = isset($result['result']) && is_string($result['result']) ? $result['result'] : 'updated';
                if (!array_key_exists($result_key, $summary)) {
                    $result_key = 'updated';
                }

                $summary[$result_key]++;
            }

            foreach ($cancelled_property_ids as $property_id) {
                $result = $this->sync_service->mark_property_missing_by_id($property_id);
                if (is_wp_error($result)) {
                    $summary['skipped']++;
                    continue;
                }

                $result_key = isset($result['result']) && is_string($result['result']) ? $result['result'] : 'deactivated';
                if ($result_key === 'missing') {
                    $summary['deactivated']++;
                } elseif ($result_key === 'not_found') {
                    $summary['skipped']++;
                } else {
                    $summary['unchanged']++;
                }
            }

            $availability_last_access = $this->resolve_availability_last_access($state);
            $availability_probe = $this->probe_availability_changes($availability_last_access, false);
            if (is_wp_error($availability_probe)) {
                return $this->finalize_error($state, $availability_probe);
            }

            $availability_changes = isset($availability_probe['parsed']) && is_array($availability_probe['parsed'])
                ? $availability_probe['parsed']
                : [];
            $availability_changed_ids = isset($availability_changes['property_ids']) && is_array($availability_changes['property_ids'])
                ? array_values($availability_changes['property_ids'])
                : [];

            if ($availability_changed_ids !== []) {
                $this->availability_service->invalidate_cache_for_property_ids($availability_changed_ids, time());
            }

            $summary['availability_changed_ids'] = count($availability_changed_ids);

            $finished_at = time();
            $state = $this->get_state();
            $state['last_status'] = 'success';
            $state['last_error'] = '';
            $state['last_started_at'] = $started_at;
            $state['last_finished_at'] = $finished_at;
            $state['last_property_access'] = $this->format_last_access_time($finished_at);
            $state['last_availability_access'] = $this->format_last_access_time($finished_at);
            $state['last_changed_property_ids'] = $changed_property_ids;
            $state['last_cancelled_property_ids'] = $cancelled_property_ids;
            $state['last_availability_changed_ids'] = $availability_changed_ids;
            $state['summary'] = $summary;
            $this->persist_state($state);

            return [
                'summary' => $summary,
                'state' => $this->get_state(),
                'property_probe' => $property_probe,
                'availability_probe' => $availability_probe,
            ];
        } finally {
            $this->release_lock();
        }
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function probe_property_changes(?string $override_last_access = null): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_delta_refresh_missing_credentials',
                __('Please save your Barefoot API credentials before probing property changes.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $state = $this->get_state();
        $last_access = $override_last_access !== null
            ? trim($override_last_access)
            : $this->resolve_property_last_access($state);

        $started = microtime(true);
        $response = $this->api_client->fetch_last_updated_property_ids_string($settings, $last_access);
        if (is_wp_error($response)) {
            return $response;
        }

        $parsed = $this->parser->parse_last_updated_property_changes($response);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        return [
            'endpoint' => 'GetLastUpdatedPropertyIDs',
            'last_access_used' => $last_access,
            'raw_payload' => $response,
            'parsed' => $parsed,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function probe_availability_changes(?string $override_last_access = null, bool $use_test_endpoint = false): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_delta_refresh_missing_credentials',
                __('Please save your Barefoot API credentials before probing availability changes.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $state = $this->get_state();
        $last_access = $override_last_access !== null
            ? trim($override_last_access)
            : $this->resolve_availability_last_access($state);

        $started = microtime(true);
        $used_fallback_endpoint = false;
        $response = $use_test_endpoint
            ? $this->api_client->fetch_last_avail_changed_properties_test_string($settings, $last_access)
            : $this->api_client->fetch_last_avail_changed_properties_string($settings, $last_access);

        if ($use_test_endpoint && is_wp_error($response) && $this->should_fallback_from_test_endpoint($response)) {
            $response = $this->api_client->fetch_last_avail_changed_properties_string($settings, $last_access);
            $used_fallback_endpoint = !is_wp_error($response);
        }

        if (is_wp_error($response)) {
            return $response;
        }

        $parsed = $this->parser->parse_last_avail_changed_properties($response);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        return [
            'endpoint' => $use_test_endpoint ? 'GetLastAvailChangedPropertiesTest' : 'GetLastAvailChangedProperties',
            'resolved_endpoint' => ($use_test_endpoint && $used_fallback_endpoint)
                ? 'GetLastAvailChangedProperties'
                : ($use_test_endpoint ? 'GetLastAvailChangedPropertiesTest' : 'GetLastAvailChangedProperties'),
            'used_fallback_endpoint' => $used_fallback_endpoint,
            'last_access_used' => $last_access,
            'raw_payload' => $response,
            'parsed' => $parsed,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function preview_delta(
        ?string $property_last_access = null,
        ?string $availability_last_access = null,
        bool $use_test_availability_endpoint = false
    ): array|WP_Error {
        $property_probe = $this->probe_property_changes($property_last_access);
        if (is_wp_error($property_probe)) {
            return $property_probe;
        }

        $availability_probe = $this->probe_availability_changes($availability_last_access, $use_test_availability_endpoint);
        if (is_wp_error($availability_probe)) {
            return $availability_probe;
        }

        $property_changes = isset($property_probe['parsed']) && is_array($property_probe['parsed'])
            ? $property_probe['parsed']
            : [];
        $availability_changes = isset($availability_probe['parsed']) && is_array($availability_probe['parsed'])
            ? $availability_probe['parsed']
            : [];

        $would_update_ids = isset($property_changes['all_update_property_ids']) && is_array($property_changes['all_update_property_ids'])
            ? array_values($property_changes['all_update_property_ids'])
            : [];
        $would_cancel_ids = isset($property_changes['cancelled_property_ids']) && is_array($property_changes['cancelled_property_ids'])
            ? array_values($property_changes['cancelled_property_ids'])
            : [];
        $availability_changed_ids = isset($availability_changes['property_ids']) && is_array($availability_changes['property_ids'])
            ? array_values($availability_changes['property_ids'])
            : [];

        return [
            'property_probe' => $property_probe,
            'availability_probe' => $availability_probe,
            'summary' => [
                'would_update_ids' => $would_update_ids,
                'would_cancel_ids' => $would_cancel_ids,
                'would_invalidate_availability_cache' => $availability_changed_ids !== [],
                'availability_changed_ids' => $availability_changed_ids,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function maybe_queue_refresh(string $reason = 'preflight'): array
    {
        $state = $this->get_state();
        $now = time();
        $stale = $this->is_stale($state, $now);
        $lock_active = $this->is_lock_active();
        $queued = false;

        if ($stale && !$lock_active) {
            $last_scheduled_at = isset($state['last_scheduled_at']) ? (int) $state['last_scheduled_at'] : 0;
            $cooldown = max(10, (int) apply_filters('barefoot_engine_delta_refresh_queue_cooldown', self::DEFAULT_QUEUE_COOLDOWN));

            if ($last_scheduled_at <= 0 || ($now - $last_scheduled_at) >= $cooldown) {
                wp_schedule_single_event($now + 5, self::CRON_HOOK);
                $state['last_scheduled_at'] = $now;
                $this->persist_state($state);
                $queued = true;
            }
        }

        return [
            'reason' => $reason,
            'stale' => $stale,
            'lock_active' => $lock_active,
            'queued' => $queued,
            'next_scheduled_at' => (int) wp_next_scheduled(self::CRON_HOOK),
            'state' => $this->get_state(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get_state(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }

        return array_merge(
            [
                'last_started_at' => 0,
                'last_finished_at' => 0,
                'last_status' => 'idle',
                'last_error' => '',
                'last_property_access' => '',
                'last_availability_access' => '',
                'last_scheduled_at' => 0,
                'summary' => [
                    'created' => 0,
                    'updated' => 0,
                    'unchanged' => 0,
                    'deactivated' => 0,
                    'skipped' => 0,
                    'changed_property_ids' => 0,
                    'cancelled_property_ids' => 0,
                    'availability_changed_ids' => 0,
                    'forced' => 0,
                ],
                'last_changed_property_ids' => [],
                'last_cancelled_property_ids' => [],
                'last_availability_changed_ids' => [],
            ],
            $stored
        );
    }

    public function is_lock_active(): bool
    {
        return get_transient(self::LOCK_TRANSIENT_KEY) !== false;
    }

    public static function clear_scheduled_events(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function is_stale(array $state, int $timestamp): bool
    {
        $ttl = max(
            60,
            (int) apply_filters('barefoot_engine_delta_refresh_preflight_ttl', self::DEFAULT_PRECHECK_TTL)
        );
        $last_finished_at = isset($state['last_finished_at']) ? (int) $state['last_finished_at'] : 0;

        if ($last_finished_at <= 0) {
            return true;
        }

        return ($timestamp - $last_finished_at) >= $ttl;
    }

    private function acquire_lock(): bool
    {
        if ($this->is_lock_active()) {
            return false;
        }

        $ttl = max(
            30,
            (int) apply_filters('barefoot_engine_delta_refresh_lock_ttl', self::DEFAULT_LOCK_TTL)
        );

        return set_transient(self::LOCK_TRANSIENT_KEY, (string) time(), $ttl);
    }

    private function release_lock(): void
    {
        delete_transient(self::LOCK_TRANSIENT_KEY);
    }

    private function format_last_access_time(int $timestamp): string
    {
        return wp_date('Y-m-d H:i:s', $timestamp, wp_timezone());
    }

    /**
     * @param array<string, mixed> $state
     */
    private function resolve_property_last_access(array $state): string
    {
        $last_access = isset($state['last_property_access']) ? trim((string) $state['last_property_access']) : '';
        if ($last_access !== '') {
            return $last_access;
        }

        $sync_state = get_option(Property_Sync_Service::SYNC_STATE_OPTION_KEY, []);
        if (is_array($sync_state)) {
            $last_finished_at = isset($sync_state['last_finished_at']) ? (int) $sync_state['last_finished_at'] : 0;
            if ($last_finished_at > 0) {
                return $this->format_last_access_time($last_finished_at);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $state
     */
    private function resolve_availability_last_access(array $state): string
    {
        $last_access = isset($state['last_availability_access']) ? trim((string) $state['last_availability_access']) : '';
        if ($last_access !== '') {
            return $last_access;
        }

        $availability_state = get_option(Property_Availability_Service::AVAILABILITY_STATE_OPTION_KEY, []);
        if (is_array($availability_state)) {
            $stored_last_access = isset($availability_state['last_access']) ? trim((string) $availability_state['last_access']) : '';
            if ($stored_last_access !== '') {
                return $stored_last_access;
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $state
     */
    private function persist_state(array $state): void
    {
        update_option(self::OPTION_KEY, $state, false);
    }

    /**
     * @param array<string, mixed> $state
     * @return WP_Error
     */
    private function finalize_error(array $state, WP_Error $error): WP_Error
    {
        $state['last_status'] = 'error';
        $state['last_error'] = $error->get_error_message();
        $state['last_finished_at'] = time();
        $this->persist_state($state);

        return $error;
    }

    private function should_fallback_from_test_endpoint(WP_Error $error): bool
    {
        $haystack = strtolower(trim($error->get_error_message()));
        $data = $error->get_error_data();

        if (is_array($data)) {
            $details = isset($data['details']) ? (string) $data['details'] : '';
            if ($details !== '') {
                $haystack .= ' ' . strtolower($details);
            }
        }

        if ($haystack === '') {
            return false;
        }

        return str_contains($haystack, 'method name is not valid')
            || str_contains($haystack, 'invalidoperationexception')
            || str_contains($haystack, 'server did not recognize');
    }
}
