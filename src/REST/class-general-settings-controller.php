<?php

namespace BarefootEngine\REST;

use BarefootEngine\Services\General_Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class General_Settings_Controller
{
    private const NAMESPACE = 'barefoot-engine/v1';
    private const REST_BASE = 'general-settings';

    private General_Settings $settings;

    public function __construct(?General_Settings $settings = null)
    {
        $this->settings = $settings ?? new General_Settings();
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
                'fontOptions' => $this->settings->get_font_options(),
                'config' => [
                    'min' => General_Settings::FONT_SIZE_MIN,
                    'max' => General_Settings::FONT_SIZE_MAX,
                    'step' => General_Settings::FONT_SIZE_STEP,
                ],
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
                'barefoot_engine_general_invalid_payload',
                __('Invalid request payload.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $prepared = $this->settings->prepare_for_save($params);
        if (!empty($prepared['errors'])) {
            return new WP_Error(
                'barefoot_engine_general_validation_failed',
                __('Please fix the highlighted fields and try again.', 'barefoot-engine'),
                [
                    'status' => 400,
                    'fields' => $prepared['errors'],
                ]
            );
        }

        $saved = $this->settings->persist($prepared['settings']);

        return rest_ensure_response(
            [
                'success' => true,
                'message' => __('General settings saved successfully.', 'barefoot-engine'),
                'data' => $saved,
                'fontOptions' => $this->settings->get_font_options(),
                'config' => [
                    'min' => General_Settings::FONT_SIZE_MIN,
                    'max' => General_Settings::FONT_SIZE_MAX,
                    'step' => General_Settings::FONT_SIZE_STEP,
                ],
            ]
        );
    }
}
