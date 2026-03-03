<?php

namespace BarefootEngine\REST;

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

    public function __construct(?Property_Sync_Service $sync_service = null)
    {
        $this->sync_service = $sync_service ?? new Property_Sync_Service();
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
     * @return array<string, mixed>
     */
    private function build_settings_payload(): array
    {
        return [
            'sync_state' => $this->sync_service->get_sync_state(),
        ];
    }
}
