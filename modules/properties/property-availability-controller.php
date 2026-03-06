<?php

namespace BarefootEngine\REST;

use BarefootEngine\Properties\Property_Availability_Service;
use BarefootEngine\Properties\Property_Delta_Refresh_Service;
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
    private Property_Delta_Refresh_Service $delta_refresh_service;

    public function __construct(
        ?Property_Availability_Service $availability_service = null,
        ?Property_Delta_Refresh_Service $delta_refresh_service = null
    )
    {
        $this->availability_service = $availability_service ?? new Property_Availability_Service();
        $this->delta_refresh_service = $delta_refresh_service ?? new Property_Delta_Refresh_Service();
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

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/preflight',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'preflight'],
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

    public function preflight(WP_REST_Request $request): WP_REST_Response
    {
        $reason = $request->get_param('reason');
        $normalized_reason = is_scalar($reason) && trim((string) $reason) !== ''
            ? trim((string) $reason)
            : 'availability-preflight';
        $result = $this->delta_refresh_service->maybe_queue_refresh($normalized_reason);

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
