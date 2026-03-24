<?php

namespace BarefootEngine\REST;

use BarefootEngine\Properties\Property_Booking_Checkout_Service;
use WP_REST_Request;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Booking_Checkout_Controller
{
    private const NAMESPACE = 'barefoot-engine/v1';
    private const REST_BASE = 'booking-checkout';

    private Property_Booking_Checkout_Service $checkout_service;

    public function __construct(?Property_Booking_Checkout_Service $checkout_service = null)
    {
        $this->checkout_service = $checkout_service ?? new Property_Booking_Checkout_Service();
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/start',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'start_session'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/session',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'create_session'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );

        register_rest_route(
            self::NAMESPACE,
            '/' . self::REST_BASE . '/complete',
            [
                [
                    'methods' => WP_REST_Server::CREATABLE,
                    'callback' => [$this, 'complete_session'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    /**
     * @return \WP_REST_Response|\WP_Error
     */
    public function start_session(WP_REST_Request $request)
    {
        $result = $this->checkout_service->start_session(
            [
                'property_id' => $this->read_string_param($request, 'property_id'),
                'check_in' => $this->read_string_param($request, 'check_in'),
                'check_out' => $this->read_string_param($request, 'check_out'),
                'guests' => $this->read_int_param($request, 'guests', 1),
                'reztypeid' => $this->read_nullable_int_param($request, 'reztypeid'),
                'payment_mode' => $this->read_string_param($request, 'payment_mode'),
                'portal_id' => $this->read_string_param($request, 'portal_id'),
                'source_of_business' => $this->read_string_param($request, 'source_of_business'),
                'redirect_url' => $this->read_string_param($request, 'redirect_url'),
                'existing_session_token' => $this->read_string_param($request, 'existing_session_token'),
            ]
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
     * @return \WP_REST_Response|\WP_Error
     */
    public function create_session(WP_REST_Request $request)
    {
        $result = $this->checkout_service->create_session(
            [
                'session_token' => $this->read_string_param($request, 'session_token'),
                'property_id' => $this->read_string_param($request, 'property_id'),
                'check_in' => $this->read_string_param($request, 'check_in'),
                'check_out' => $this->read_string_param($request, 'check_out'),
                'guests' => $this->read_int_param($request, 'guests', 1),
                'reztypeid' => $this->read_nullable_int_param($request, 'reztypeid'),
                'payment_mode' => $this->read_string_param($request, 'payment_mode'),
                'portal_id' => $this->read_string_param($request, 'portal_id'),
                'source_of_business' => $this->read_string_param($request, 'source_of_business'),
                'guest' => $this->read_array_param($request, 'guest'),
                'quote' => $this->read_array_param($request, 'quote'),
            ]
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
     * @return \WP_REST_Response|\WP_Error
     */
    public function complete_session(WP_REST_Request $request)
    {
        $result = $this->checkout_service->complete_session(
            [
                'session_token' => $this->read_string_param($request, 'session_token'),
                'property_id' => $this->read_string_param($request, 'property_id'),
                'check_in' => $this->read_string_param($request, 'check_in'),
                'check_out' => $this->read_string_param($request, 'check_out'),
                'guests' => $this->read_int_param($request, 'guests', 1),
                'payment' => $this->read_array_param($request, 'payment'),
            ]
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

    /**
     * @return array<string, mixed>
     */
    private function read_array_param(WP_REST_Request $request, string $key): array
    {
        $value = $request->get_param($key);

        return is_array($value) ? $value : [];
    }
}
