<?php

namespace BarefootEngine\REST;

use BarefootEngine\Services\Api_Integration_Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Api_Integration_Controller
{
    private const NAMESPACE = 'barefoot-engine/v1';
    private const REST_BASE = 'api-integration';

    private Api_Integration_Settings $settings;

    public function __construct(?Api_Integration_Settings $settings = null)
    {
        $this->settings = $settings ?? new Api_Integration_Settings();
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE,
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_settings'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'save_settings'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/test',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'test_connection'],
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
                'data' => $this->settings->get_public_settings(),
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function save_settings(WP_REST_Request $request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            return new WP_Error(
                'barefoot_engine_api_invalid_payload',
                __('Invalid request payload.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $saved_settings = $this->settings->save($params);

        return rest_ensure_response(
            [
                'success' => true,
                'message' => __('API Integration settings saved successfully.', 'barefoot-engine'),
                'data' => $this->settings->to_public_settings($saved_settings),
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function test_connection(WP_REST_Request $request)
    {
        unset($request);

        $settings = $this->settings->get_settings();
        if (!$this->settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_api_incomplete_credentials',
                __('Please save a username, company ID, and password before testing the connection.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return rest_ensure_response(
            [
                'success' => true,
                'message' => __('Mock connection test succeeded.', 'barefoot-engine'),
                'data' => [
                    'checked_at' => time(),
                ],
            ]
        );
    }
}
