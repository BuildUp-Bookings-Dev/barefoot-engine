<?php

namespace BarefootEngine\REST;

use BarefootEngine\Properties\Property_Availability_Service;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Availability_Controller
{
    private const NAMESPACE = 'barefoot-engine/v1';
    private const REST_BASE = 'availability';

    private Property_Availability_Service $availability_service;

    public function __construct(?Property_Availability_Service $availability_service = null)
    {
        $this->availability_service = $availability_service ?? new Property_Availability_Service();
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/search',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'search'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    /**
     * @return WP_REST_Response|\WP_Error
     */
    public function search(WP_REST_Request $request)
    {
        $check_in = $this->read_date_param($request, 'check_in');
        $check_out = $this->read_date_param($request, 'check_out');
        $force_refresh = rest_sanitize_boolean($request->get_param('force_refresh'));

        $result = $this->availability_service->search_available_property_ids($check_in, $check_out, $force_refresh);
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

    private function read_date_param(WP_REST_Request $request, string $key): string
    {
        $value = $request->get_param($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
