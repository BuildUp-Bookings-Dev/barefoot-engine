<?php

namespace BarefootEngine\REST;

use BarefootEngine\Services\Property_Alias_Settings;
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

    private Property_Alias_Settings $alias_settings;
    private Property_Sync_Service $sync_service;

    public function __construct(
        ?Property_Alias_Settings $alias_settings = null,
        ?Property_Sync_Service $sync_service = null
    ) {
        $this->alias_settings = $alias_settings ?? new Property_Alias_Settings();
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
            '/' . self::REST_BASE . '/aliases',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'save_aliases'],
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
    public function save_aliases(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            return new WP_Error(
                'barefoot_engine_property_invalid_payload',
                __('Invalid request payload.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $aliases = isset($params['aliases']) && is_array($params['aliases']) ? $params['aliases'] : null;
        if ($aliases === null) {
            return new WP_Error(
                'barefoot_engine_property_invalid_aliases',
                __('Property aliases payload is missing or invalid.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $this->alias_settings->save_aliases($aliases);

        return rest_ensure_response(
            [
                'success' => true,
                'message' => __('Property aliases saved successfully.', 'barefoot-engine'),
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
     * @return array<string, mixed>
     */
    private function build_settings_payload(): array
    {
        $sync_state = $this->sync_service->get_sync_state();
        $field_keys = isset($sync_state['field_keys']) && is_array($sync_state['field_keys']) ? $sync_state['field_keys'] : [];
        $amenity_labels = isset($sync_state['amenity_labels']) && is_array($sync_state['amenity_labels']) ? $sync_state['amenity_labels'] : [];

        return [
            'aliases' => $this->alias_settings->get_aliases(),
            'field_keys' => $field_keys,
            'amenity_labels' => $amenity_labels,
            'alias_rows' => $this->alias_settings->build_alias_rows($field_keys, $amenity_labels),
            'sync_state' => $sync_state,
        ];
    }
}
