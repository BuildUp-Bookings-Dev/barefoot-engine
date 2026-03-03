<?php

namespace BarefootEngine\REST;

use BarefootEngine\Services\Updates_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Updates_Controller
{
    private const NAMESPACE = 'barefoot-engine/v1';
    private const REST_BASE = 'updates';

    private Updates_Service $updates;

    public function __construct(?Updates_Service $updates = null)
    {
        $this->updates = $updates ?? new Updates_Service();
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/status',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_status'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/check',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'check_now'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/releases',
            [
                [
                    'methods' => WP_REST_Server::READABLE,
                    'callback' => [$this, 'get_releases'],
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

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get_status(WP_REST_Request $request)
    {
        unset($request);

        $status = $this->updates->get_status();
        if (is_wp_error($status)) {
            return $status;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => $status,
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function check_now(WP_REST_Request $request)
    {
        unset($request);

        $result = $this->updates->check_now();
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'message' => __('Update status refreshed successfully.', 'barefoot-engine'),
                'data' => $result,
            ]
        );
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function get_releases(WP_REST_Request $request)
    {
        unset($request);

        $releases = $this->updates->get_recent_releases();
        if (is_wp_error($releases)) {
            return $releases;
        }

        return rest_ensure_response(
            [
                'success' => true,
                'data' => [
                    'releases' => $releases,
                ],
            ]
        );
    }
}
