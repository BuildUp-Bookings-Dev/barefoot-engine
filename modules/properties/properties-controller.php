<?php

namespace BarefootEngine\REST;

use BarefootEngine\Properties\Property_Delta_Refresh_Service;
use BarefootEngine\Services\Property_Sync_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Properties_Controller
{
    private const NAMESPACE = 'barefoot-engine/v1';
    private const REST_BASE = 'properties';

    private Property_Sync_Service $sync_service;
    private Property_Delta_Refresh_Service $delta_refresh_service;

    public function __construct(
        ?Property_Sync_Service $sync_service = null,
        ?Property_Delta_Refresh_Service $delta_refresh_service = null
    )
    {
        $this->sync_service = $sync_service ?? new Property_Sync_Service();
        $this->delta_refresh_service = $delta_refresh_service ?? new Property_Delta_Refresh_Service();
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/settings',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_settings'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/sync',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'sync_properties'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/partial-sync',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'partial_sync_properties'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/debug/last-updated',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'debug_last_updated'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/debug/last-avail-changed',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'debug_last_availability_changed'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/debug/delta-preview',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'debug_delta_preview'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );
    }

    /**
     * @return true|WP_Error
     */
    public function permissions_check()
    {
        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error(
            'barefoot_engine_rest_forbidden',
            __('You are not allowed to manage Barefoot Engine settings.', 'barefoot-engine'),
            ['status' => 403]
        );
    }

    public function get_settings(WP_REST_Request $request): WP_REST_Response
    {
        unset($request);

        return rest_ensure_response(
            [
                'success' => true,
                'data' => $this->build_settings_payload(),
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function sync_properties(WP_REST_Request $request)
    {
        unset($request);

        $result = $this->sync_service->sync();
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'message' => __('Property sync completed successfully.', 'barefoot-engine'),
                'data' => [
                    'summary' => isset($result['summary']) && is_array($result['summary']) ? $result['summary'] : [],
                    'sync_state' => $this->sync_service->get_sync_state(),
                    'settings' => $this->build_settings_payload(),
                ],
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function partial_sync_properties(WP_REST_Request $request)
    {
        unset($request);

        $result = $this->sync_service->sync_partial();
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'message' => __('Partial property sync completed successfully.', 'barefoot-engine'),
                'data' => [
                    'summary' => isset($result['summary']) && is_array($result['summary']) ? $result['summary'] : [],
                    'sync_state' => $this->sync_service->get_sync_state(),
                    'settings' => $this->build_settings_payload(),
                ],
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function debug_last_updated(WP_REST_Request $request)
    {
        $last_access = $this->read_optional_scalar_param($request, 'last_access');
        $result = $this->delta_refresh_service->probe_property_changes($last_access !== '' ? $last_access : null);
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => $result,
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function debug_last_availability_changed(WP_REST_Request $request)
    {
        $last_access = $this->read_optional_scalar_param($request, 'last_access');
        $use_test_endpoint = rest_sanitize_boolean($request->get_param('use_test_endpoint'));
        $result = $this->delta_refresh_service->probe_availability_changes($last_access !== '' ? $last_access : null, $use_test_endpoint);
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => $result,
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function debug_delta_preview(WP_REST_Request $request)
    {
        $property_last_access = $this->read_optional_scalar_param($request, 'property_last_access');
        $availability_last_access = $this->read_optional_scalar_param($request, 'availability_last_access');
        $use_test_endpoint = rest_sanitize_boolean($request->get_param('use_test_endpoint'));

        $result = $this->delta_refresh_service->preview_delta(
            $property_last_access !== '' ? $property_last_access : null,
            $availability_last_access !== '' ? $availability_last_access : null,
            $use_test_endpoint
        );
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => $result,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function build_settings_payload(): array
    {
        return [
            'sync_state' => $this->sync_service->get_sync_state(),
            'delta_state' => $this->delta_refresh_service->get_state(),
        ];
    }

    private function read_optional_scalar_param(WP_REST_Request $request, string $key): string
    {
        $value = $request->get_param($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
