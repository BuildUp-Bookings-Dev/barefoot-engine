<?php

namespace BarefootEngine\REST;

use BarefootEngine\Properties\Property_Booking_Service;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Booking_Controller
{
    private const NAMESPACE = 'barefoot-engine/v1';
    private const REST_BASE = 'booking';

    private Property_Booking_Service $booking_service;

    public function __construct(?Property_Booking_Service $booking_service = null)
    {
        $this->booking_service = $booking_service ?? new Property_Booking_Service();
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/calendar',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'calendar'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/quote',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'quote'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    /**
     * @return WP_REST_Response|\WP_Error
     */
    public function calendar(WP_REST_Request $request)
    {
        $property_id = $this->read_string_param($request, 'property_id');
        $month_start = $this->read_string_param($request, 'month_start');
        $month_end = $this->read_string_param($request, 'month_end');

        $result = $this->booking_service->get_calendar_data($property_id, $month_start, $month_end);
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
     * @return WP_REST_Response|\WP_Error
     */
    public function quote(WP_REST_Request $request)
    {
        $property_id = $this->read_string_param($request, 'property_id');
        $check_in = $this->read_string_param($request, 'check_in');
        $check_out = $this->read_string_param($request, 'check_out');
        $guests = $this->read_int_param($request, 'guests', 1);
        $reztypeid = $this->read_nullable_int_param($request, 'reztypeid');

        $result = $this->booking_service->get_quote_data($property_id, $check_in, $check_out, $guests, $reztypeid);
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

    private function read_string_param(WP_REST_Request $request, string $key): string
    {
        $value = $request->get_param($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function read_int_param(WP_REST_Request $request, string $key, int $default): int
    {
        $value = $request->get_param($key);
        if (!is_scalar($value) || !is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    private function read_nullable_int_param(WP_REST_Request $request, string $key): ?int
    {
        $value = $request->get_param($key);
        if (!is_scalar($value) || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
