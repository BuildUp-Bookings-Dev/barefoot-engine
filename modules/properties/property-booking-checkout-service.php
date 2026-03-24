<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Api_Integration_Settings;
use BarefootEngine\Services\Barefoot_Api_Client;
use BarefootEngine\Services\Property_Sync_Service;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Booking_Checkout_Service
{
    private const DEFAULT_REZTYPE_ID = 26;
    private const DEFAULT_SESSION_TTL = 1800;
    private const DEFAULT_PAYMENT_MODE = 'TRUE';
    private const SESSION_TRANSIENT_PREFIX = 'barefoot_engine_booking_checkout_';
    private const DEFAULT_CONFIRMATION_PATH = '/booking-confirmed';
    private const DEFAULT_CONFIRMATION_ICS_PATH = '/booking-confirmed/ics';

    private Barefoot_Api_Client $api_client;
    private Api_Integration_Settings $api_settings;
    private Property_Parser $parser;
    private Property_Booking_Records $booking_records;

    public function __construct(
        ?Barefoot_Api_Client $api_client = null,
        ?Api_Integration_Settings $api_settings = null,
        ?Property_Parser $parser = null,
        ?Property_Booking_Records $booking_records = null
    ) {
        $this->api_client = $api_client ?? new Barefoot_Api_Client();
        $this->api_settings = $api_settings ?? new Api_Integration_Settings();
        $this->parser = $parser ?? new Property_Parser();
        $this->booking_records = $booking_records ?? new Property_Booking_Records();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_property_summary(string $property_id): array|WP_Error
    {
        $normalized_property_id = $this->normalize_property_id($property_id);
        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_checkout_missing_property_id',
                __('A valid Barefoot Property ID is required for checkout.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $post_id = $this->find_property_post_id($normalized_property_id);
        if ($post_id <= 0) {
            return new WP_Error(
                'barefoot_engine_checkout_property_not_found',
                __('The requested property could not be found locally.', 'barefoot-engine'),
                ['status' => 404]
            );
        }

        $fields = get_post_meta($post_id, '_be_property_fields', true);
        if (!is_array($fields)) {
            $fields = [];
        }

        $post = get_post($post_id);
        $title = $this->resolve_property_title($fields, $post instanceof \WP_Post ? $post : null);
        $address = $this->resolve_property_address($fields);
        $image_url = $this->resolve_property_image_url($post_id, $fields);
        $guest_count = $this->resolve_guest_count($post_id, $fields);
        $bedrooms = $this->resolve_bedroom_count($post_id, $fields);
        $bathrooms = $this->resolve_bathroom_count($post_id, $fields);
        $coordinates = $this->resolve_property_coordinates($fields);

        return [
            'postId' => $post_id,
            'propertyId' => $normalized_property_id,
            'title' => $title,
            'address' => $address,
            'imageUrl' => $image_url,
            'permalink' => get_permalink($post_id) ?: '',
            'coordinates' => $coordinates,
            'stats' => [
                'sleeps' => $guest_count,
                'bedrooms' => $bedrooms,
                'bathrooms' => $bathrooms,
            ],
        ];
    }

    public function get_confirmation_page_url(string $confirmation_token): string
    {
        $normalized_token = sanitize_text_field(trim($confirmation_token));
        $base_url = home_url(user_trailingslashit(trim(self::DEFAULT_CONFIRMATION_PATH, '/')));

        return add_query_arg(
            ['confirmation' => $normalized_token],
            $base_url
        );
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_confirmation_page_context(string $confirmation_token): array|WP_Error
    {
        $normalized_token = sanitize_text_field(trim($confirmation_token));
        if ($normalized_token === '') {
            return new WP_Error(
                'barefoot_engine_confirmation_missing_token',
                __('A valid booking confirmation link is required.', 'barefoot-engine'),
                ['status' => 404]
            );
        }

        $token_hash = $this->build_confirmation_token_hash($normalized_token);
        $booking_record_id = $this->booking_records->find_record_by_confirmation_token_hash($token_hash);
        if ($booking_record_id <= 0) {
            return new WP_Error(
                'barefoot_engine_confirmation_not_found',
                __('This booking confirmation link is invalid or has expired.', 'barefoot-engine'),
                ['status' => 404]
            );
        }

        $record = $this->booking_records->get_record_snapshot($booking_record_id);
        if ($record === []) {
            return new WP_Error(
                'barefoot_engine_confirmation_not_found',
                __('We could not load this booking confirmation.', 'barefoot-engine'),
                ['status' => 404]
            );
        }

        if (($record['status'] ?? '') !== Property_Booking_Records::STATUS_BOOKING_SUCCESS) {
            return new WP_Error(
                'barefoot_engine_confirmation_not_ready',
                __('This booking confirmation is not available yet.', 'barefoot-engine'),
                ['status' => 410]
            );
        }

        $property_summary = $this->resolve_confirmation_property_summary($record);
        $check_in = $this->normalize_ymd_date((string) ($record['check_in'] ?? ''));
        $check_out = $this->normalize_ymd_date((string) ($record['check_out'] ?? ''));
        $nights = max(1, $this->calculate_date_diff_days($check_in, $check_out));
        $guests = $this->normalize_guest_count($record['guests'] ?? 1);
        $totals = is_array($record['totals'] ?? null) ? $record['totals'] : [];
        $payment_schedule = is_array($record['payment_schedule'] ?? null) ? $record['payment_schedule'] : [];
        $deposit_amount = isset($record['deposit_amount']) && is_numeric($record['deposit_amount'])
            ? $this->normalize_money((float) $record['deposit_amount'])
            : $this->resolve_deposit_amount($payment_schedule, $totals);
        $payable_amount = isset($record['payable_amount']) && is_numeric($record['payable_amount'])
            ? $this->normalize_money((float) $record['payable_amount'])
            : $this->resolve_payable_amount($payment_schedule, $totals, $deposit_amount);
        $coordinates = $this->extract_confirmation_coordinates($property_summary);
        $property_title = $this->clean_string($property_summary['title'] ?? __('Property', 'barefoot-engine'));
        $property_address = $this->clean_string($property_summary['address'] ?? '');
        $folio_id = $this->clean_string($record['folio_id'] ?? '');

        $context = [
            'valid' => true,
            'bookingRecordId' => $booking_record_id,
            'folioId' => $folio_id,
            'property' => [
                'title' => $property_title,
                'address' => $property_address,
                'imageUrl' => $this->clean_string($property_summary['imageUrl'] ?? ''),
                'permalink' => $this->clean_string($property_summary['permalink'] ?? ''),
                'stats' => is_array($property_summary['stats'] ?? null) ? $property_summary['stats'] : [],
            ],
            'stay' => [
                'checkIn' => $check_in,
                'checkOut' => $check_out,
                'checkInDisplay' => $this->format_confirmation_display_date($check_in),
                'checkOutDisplay' => $this->format_confirmation_display_date($check_out),
                'nights' => $nights,
                'nightsLabel' => sprintf(
                    /* translators: %d is the number of nights booked. */
                    _n('%d night', '%d nights', $nights, 'barefoot-engine'),
                    $nights
                ),
                'guests' => $guests,
                'guestsLabel' => sprintf(
                    /* translators: %d is the number of guests in the booking. */
                    _n('%d guest', '%d guests', $guests, 'barefoot-engine'),
                    $guests
                ),
            ],
            'payments' => [
                'rent' => isset($totals['subtotal']) && is_numeric($totals['subtotal'])
                    ? $this->normalize_money((float) $totals['subtotal'])
                    : 0.0,
                'tax' => isset($totals['tax_total']) && is_numeric($totals['tax_total'])
                    ? $this->normalize_money((float) $totals['tax_total'])
                    : 0.0,
                'deposit' => $deposit_amount,
                'total' => isset($totals['grand_total']) && is_numeric($totals['grand_total'])
                    ? $this->normalize_money((float) $totals['grand_total'])
                    : 0.0,
                'payable' => $payable_amount,
                'charged' => isset($record['amount']) && is_numeric($record['amount'])
                    ? $this->normalize_money((float) $record['amount'])
                    : 0.0,
                'rentLabel' => __('Rent', 'barefoot-engine'),
                'taxLabel' => __('Local and State Taxes', 'barefoot-engine'),
                'depositLabel' => __('Deposit Amount', 'barefoot-engine'),
                'totalLabel' => __('Total', 'barefoot-engine'),
                'payableLabel' => __('Payable Amount', 'barefoot-engine'),
            ],
            'map' => [
                'available' => $coordinates !== null,
                'embedUrl' => $coordinates !== null
                    ? $this->build_confirmation_map_embed_url($coordinates['lat'], $coordinates['lng'])
                    : '',
                'lat' => $coordinates['lat'] ?? null,
                'lng' => $coordinates['lng'] ?? null,
            ],
            'calendar' => $this->build_confirmation_calendar_links(
                $normalized_token,
                $property_title,
                $property_address,
                $check_in,
                $check_out,
                $folio_id
            ),
            'paymentSummary' => is_array($record['payment_summary'] ?? null) ? $record['payment_summary'] : [],
        ];

        return $context;
    }

    /**
     * @return array{filename: string, content: string}|WP_Error
     */
    public function get_confirmation_ics_payload(string $confirmation_token): array|WP_Error
    {
        $context = $this->get_confirmation_page_context($confirmation_token);
        if (is_wp_error($context)) {
            return $context;
        }

        $property = is_array($context['property'] ?? null) ? $context['property'] : [];
        $stay = is_array($context['stay'] ?? null) ? $context['stay'] : [];
        $folio_id = $this->clean_string($context['folioId'] ?? '');
        $title = $this->clean_string($property['title'] ?? __('Property', 'barefoot-engine'));
        $address = $this->clean_string($property['address'] ?? '');
        $check_in = $this->clean_string($stay['checkIn'] ?? '');
        $check_out = $this->clean_string($stay['checkOut'] ?? '');
        $uid_suffix = preg_replace('/[^a-z0-9]+/i', '-', strtolower($title)) ?: 'booking';
        $uid = sprintf('barefoot-engine-%s-%s@%s', $uid_suffix, $this->build_confirmation_token_hash($confirmation_token), wp_parse_url(home_url(), PHP_URL_HOST) ?: 'localhost');
        $description = $folio_id !== ''
            ? sprintf(
                /* translators: %s is the reservation folio id. */
                __('Reservation ID: %s', 'barefoot-engine'),
                $folio_id
            )
            : __('Booking confirmation', 'barefoot-engine');
        $content = $this->build_ics_content(
            [
                'uid' => $uid,
                'title' => sprintf(
                    /* translators: %s is the property title. */
                    __('Stay at %s', 'barefoot-engine'),
                    $title
                ),
                'description' => $description,
                'location' => $address,
                'check_in' => $check_in,
                'check_out' => $check_out,
            ]
        );
        $filename = sanitize_file_name(sprintf(
            'booking-%s-%s.ics',
            $uid_suffix,
            $check_in !== '' ? str_replace('-', '', $check_in) : gmdate('Ymd')
        ));

        return [
            'filename' => $filename,
            'content' => $content,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function start_session(array $payload): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        $property_id = $this->normalize_property_id((string) ($payload['property_id'] ?? ''));
        $check_in = $this->normalize_ymd_date((string) ($payload['check_in'] ?? ''));
        $check_out = $this->normalize_ymd_date((string) ($payload['check_out'] ?? ''));
        $guests = $this->normalize_guest_count($payload['guests'] ?? 1);
        $reztypeid = $this->normalize_positive_int($payload['reztypeid'] ?? self::DEFAULT_REZTYPE_ID, self::DEFAULT_REZTYPE_ID);
        $payment_mode = $this->resolve_payment_mode($payload['payment_mode'] ?? '', $settings);
        $redirect_url = $this->clean_string($payload['redirect_url'] ?? '/booking-confirmation');
        $existing_session_token = sanitize_text_field((string) ($payload['existing_session_token'] ?? ''));
        $portal_id = sanitize_text_field((string) ($payload['portal_id'] ?? ''));
        $source_of_business = sanitize_text_field((string) ($payload['source_of_business'] ?? ''));

        if ($property_id === '') {
            return new WP_Error(
                'barefoot_engine_checkout_missing_property_id',
                __('A valid Barefoot Property ID is required for checkout.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($check_in === '' || $check_out === '' || $check_out <= $check_in) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_date_range',
                __('A valid check-in and check-out range is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $summary = $this->get_property_summary($property_id);
        if (is_wp_error($summary)) {
            return $summary;
        }

        $replaced_booking_record_id = 0;
        if ($existing_session_token !== '') {
            $existing_session = $this->load_session_if_exists($existing_session_token);
            if (is_array($existing_session)) {
                $replaced_booking_record_id = isset($existing_session['booking_record_id']) && is_numeric($existing_session['booking_record_id'])
                    ? (int) $existing_session['booking_record_id']
                    : 0;
                if ($replaced_booking_record_id > 0) {
                    $this->booking_records->update_record(
                        $replaced_booking_record_id,
                        Property_Booking_Records::STATUS_SUPERSEDED,
                        [
                            'diagnostics' => [
                                'superseded_by_new_start' => true,
                            ],
                        ],
                        __('Session superseded by a new BOOK NOW request.', 'barefoot-engine')
                    );
                }
            }

            delete_transient($this->build_session_transient_key($existing_session_token));
        }

        $session_token = $this->generate_session_token();
        $resolved_redirect_url = $this->resolve_redirect_url(
            $redirect_url,
            $property_id,
            $check_in,
            $check_out,
            $guests,
            $reztypeid
        );

        $record_id = $this->booking_records->create_record(
            [
                'status' => Property_Booking_Records::STATUS_STARTED,
                'property_id' => $property_id,
                'check_in' => $check_in,
                'check_out' => $check_out,
                'guests' => $guests,
                'reztypeid' => $reztypeid,
                'payment_mode' => $payment_mode,
                'portal_id' => $portal_id,
                'property_summary' => $summary,
                'session_token_hash' => $this->build_session_token_hash($session_token),
                'diagnostics' => [
                    'started_from' => 'booking_widget',
                    'replaced_booking_record_id' => $replaced_booking_record_id,
                ],
                'event_label' => __('Session started from BOOK NOW.', 'barefoot-engine'),
            ]
        );

        $session_payload = [
            'property_id' => $property_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'guests' => $guests,
            'reztypeid' => $reztypeid,
            'payment_mode' => $payment_mode,
            'portal_id' => $portal_id,
            'source_of_business' => $source_of_business,
            'property_summary' => $summary,
            'booking_record_id' => $record_id > 0 ? $record_id : 0,
            'created_at' => time(),
        ];

        set_transient($this->build_session_transient_key($session_token), $session_payload, $this->get_session_ttl());

        return [
            'status' => 'started',
            'sessionToken' => $session_token,
            'bookingRecordId' => $record_id > 0 ? $record_id : null,
            'propertySummary' => $summary,
            'staySummary' => [
                'checkIn' => $check_in,
                'checkOut' => $check_out,
                'guests' => $guests,
                'reztypeid' => $reztypeid,
            ],
            'redirectUrl' => $resolved_redirect_url,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function create_session(array $payload): array|WP_Error
    {
        $settings = $this->api_settings->get_settings();
        $property_id = $this->normalize_property_id((string) ($payload['property_id'] ?? ''));
        $check_in = $this->normalize_ymd_date((string) ($payload['check_in'] ?? ''));
        $check_out = $this->normalize_ymd_date((string) ($payload['check_out'] ?? ''));
        $session_token = sanitize_text_field((string) ($payload['session_token'] ?? ''));
        $guests = $this->normalize_guest_count($payload['guests'] ?? 1);
        $reztypeid = $this->normalize_positive_int($payload['reztypeid'] ?? self::DEFAULT_REZTYPE_ID, self::DEFAULT_REZTYPE_ID);
        $payment_mode = $this->resolve_payment_mode($payload['payment_mode'] ?? '', $settings);
        $portal_id = sanitize_text_field((string) ($payload['portal_id'] ?? ''));
        $source_of_business = sanitize_text_field((string) ($payload['source_of_business'] ?? ''));

        if ($property_id === '') {
            return new WP_Error(
                'barefoot_engine_checkout_missing_property_id',
                __('A valid Barefoot Property ID is required for checkout.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($check_in === '' || $check_out === '' || $check_out <= $check_in) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_date_range',
                __('A valid check-in and check-out range is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $summary = $this->get_property_summary($property_id);
        if (is_wp_error($summary)) {
            return $summary;
        }

        $guest_details = $this->normalize_guest_details($payload['guest'] ?? []);
        if (is_wp_error($guest_details)) {
            return $guest_details;
        }

        $existing_session = null;
        $booking_record_id = 0;

        if ($session_token !== '') {
            $loaded_session = $this->load_session($session_token);
            if (is_wp_error($loaded_session)) {
                return $loaded_session;
            }

            $existing_session = $loaded_session;
            $booking_record_id = isset($loaded_session['booking_record_id']) && is_numeric($loaded_session['booking_record_id'])
                ? (int) $loaded_session['booking_record_id']
                : 0;

            $existing_property_id = $this->clean_string($loaded_session['property_id'] ?? '');
            if ($existing_property_id !== '' && $existing_property_id !== $property_id) {
                if ($booking_record_id > 0) {
                    $this->booking_records->update_record(
                        $booking_record_id,
                        Property_Booking_Records::STATUS_SUPERSEDED,
                        [
                            'diagnostics' => [
                                'superseded_by_property_change' => true,
                            ],
                        ],
                        __('Session superseded due to property context change.', 'barefoot-engine')
                    );
                }

                delete_transient($this->build_session_transient_key($session_token));
                $session_token = '';
                $existing_session = null;
                $booking_record_id = 0;
            }
        }

        if ($this->is_mock_mode_enabled($settings)) {
            if ($session_token === '') {
                $session_token = $this->generate_session_token();
            }

            if ($booking_record_id <= 0) {
                $booking_record_id = $this->booking_records->create_record(
                    [
                        'status' => Property_Booking_Records::STATUS_STARTED,
                        'property_id' => $property_id,
                        'check_in' => $check_in,
                        'check_out' => $check_out,
                        'guests' => $guests,
                        'reztypeid' => $reztypeid,
                        'payment_mode' => $payment_mode,
                        'portal_id' => $portal_id,
                        'property_summary' => $summary,
                        'guest_details' => $guest_details,
                        'session_token_hash' => $this->build_session_token_hash($session_token),
                        'diagnostics' => [
                            'started_from' => $existing_session !== null ? 'checkout_resume' : 'checkout_direct',
                            'mock_mode' => true,
                        ],
                        'event_label' => __('Mock checkout session initiated.', 'barefoot-engine'),
                    ]
                );
            }

            return $this->create_mock_checkout_session(
                $session_token,
                $booking_record_id,
                $property_id,
                $check_in,
                $check_out,
                $guests,
                $reztypeid,
                $payment_mode,
                $portal_id,
                $source_of_business,
                $summary,
                $guest_details,
                is_array($payload['quote'] ?? null) ? $payload['quote'] : []
            );
        }

        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_checkout_missing_credentials',
                __('Please save your Barefoot API credentials first.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($session_token === '') {
            $session_token = $this->generate_session_token();
        }

        if ($booking_record_id <= 0) {
            $booking_record_id = $this->booking_records->create_record(
                [
                    'status' => Property_Booking_Records::STATUS_STARTED,
                    'property_id' => $property_id,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'guests' => $guests,
                    'reztypeid' => $reztypeid,
                    'payment_mode' => $payment_mode,
                    'portal_id' => $portal_id,
                    'property_summary' => $summary,
                    'guest_details' => $guest_details,
                    'session_token_hash' => $this->build_session_token_hash($session_token),
                    'diagnostics' => [
                        'started_from' => $existing_session !== null ? 'checkout_resume' : 'checkout_direct',
                    ],
                    'event_label' => __('Checkout session initiated.', 'barefoot-engine'),
                ]
            );
        }

        $is_available = $this->api_client->is_property_availability(
            $settings,
            $property_id,
            $this->format_mdy_date($check_in),
            $this->format_mdy_date($check_out)
        );
        if (is_wp_error($is_available)) {
            return $is_available;
        }

        if ($is_available !== true) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_UNAVAILABLE,
                    [
                        'property_id' => $property_id,
                        'check_in' => $check_in,
                        'check_out' => $check_out,
                        'guests' => $guests,
                        'guest_details' => $guest_details,
                        'diagnostics' => [
                            'availability_check' => 'unavailable',
                        ],
                    ],
                    __('Selected dates were unavailable during checkout session setup.', 'barefoot-engine')
                );
            }

            return [
                'available' => false,
                'status' => 'unavailable',
                'propertySummary' => $summary,
                'bookingRecordId' => $booking_record_id > 0 ? $booking_record_id : null,
            ];
        }

        $quote_response = $this->api_client->create_quote_and_get_payment_schedule_string(
            $settings,
            $property_id,
            $this->format_mdy_date($check_in),
            $this->format_mdy_date($check_out),
            $guests,
            0,
            0,
            0,
            $reztypeid
        );
        if (is_wp_error($quote_response)) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'quote_error_code' => $quote_response->get_error_code(),
                            'quote_error_message' => $quote_response->get_error_message(),
                        ],
                    ],
                    __('Failed to fetch quote/payment schedule from Barefoot.', 'barefoot-engine')
                );
            }

            return $quote_response;
        }

        if (stripos($quote_response, 'Property check rule failed') !== false) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_UNAVAILABLE,
                    [
                        'property_id' => $property_id,
                        'check_in' => $check_in,
                        'check_out' => $check_out,
                        'guests' => $guests,
                        'guest_details' => $guest_details,
                        'diagnostics' => [
                            'quote_response' => 'Property check rule failed',
                        ],
                    ],
                    __('Barefoot quote validation reported unavailable dates.', 'barefoot-engine')
                );
            }

            return [
                'available' => false,
                'status' => 'unavailable',
                'propertySummary' => $summary,
                'bookingRecordId' => $booking_record_id > 0 ? $booking_record_id : null,
            ];
        }

        $parsed_quote = $this->parser->parse_create_quote_and_payment_schedule($quote_response);
        if (is_wp_error($parsed_quote)) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'quote_parse_error_code' => $parsed_quote->get_error_code(),
                            'quote_parse_error_message' => $parsed_quote->get_error_message(),
                        ],
                    ],
                    __('Failed to parse quote/payment schedule response.', 'barefoot-engine')
                );
            }

            return $parsed_quote;
        }

        $quote_info = isset($parsed_quote['quote_info']) && is_array($parsed_quote['quote_info'])
            ? $parsed_quote['quote_info']
            : [];
        $lease_id = isset($quote_info['leaseid']) ? (int) $quote_info['leaseid'] : 0;
        if ($lease_id <= 0) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'missing_lease_id' => true,
                            'quote_payload' => isset($parsed_quote['raw_payload']) ? (string) $parsed_quote['raw_payload'] : '',
                        ],
                    ],
                    __('Barefoot quote response did not include lease ID.', 'barefoot-engine')
                );
            }

            return new WP_Error(
                'barefoot_engine_checkout_missing_lease_id',
                __('Barefoot did not return a valid lease ID for checkout.', 'barefoot-engine'),
                [
                    'status' => 502,
                    'details' => isset($parsed_quote['raw_payload']) ? (string) $parsed_quote['raw_payload'] : '',
                ]
            );
        }

        $consumer_response = $this->api_client->set_consumer_info(
            $settings,
            $this->build_consumer_info_payload($property_id, $check_in, $check_out, $guest_details, $source_of_business)
        );
        if (is_wp_error($consumer_response)) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'consumer_error_code' => $consumer_response->get_error_code(),
                            'consumer_error_message' => $consumer_response->get_error_message(),
                        ],
                    ],
                    __('Failed to save guest details to Barefoot.', 'barefoot-engine')
                );
            }

            return $consumer_response;
        }

        $tenant_id = $this->parser->parse_set_consumer_info($consumer_response);
        if (is_wp_error($tenant_id)) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'tenant_parse_error_code' => $tenant_id->get_error_code(),
                            'tenant_parse_error_message' => $tenant_id->get_error_message(),
                        ],
                    ],
                    __('Failed to parse tenant response from Barefoot.', 'barefoot-engine')
                );
            }

            return $tenant_id;
        }

        $rate_details = isset($parsed_quote['rate_details']) && is_array($parsed_quote['rate_details'])
            ? array_values($parsed_quote['rate_details'])
            : [];
        $payment_schedule = isset($parsed_quote['payment_schedule']) && is_array($parsed_quote['payment_schedule'])
            ? array_values($parsed_quote['payment_schedule'])
            : [];
        $totals = $this->calculate_quote_totals(
            $rate_details,
            max(1, $this->calculate_date_diff_days($check_in, $check_out))
        );
        $deposit_amount = $this->resolve_deposit_amount($payment_schedule, $totals);
        $payable_amount = $this->resolve_payable_amount($payment_schedule, $totals, $deposit_amount);

        $session_payload = [
            'property_id' => $property_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'guests' => $guests,
            'reztypeid' => $reztypeid,
            'payment_mode' => $payment_mode,
            'portal_id' => $portal_id,
            'source_of_business' => $source_of_business,
            'lease_id' => $lease_id,
            'tenant_id' => $tenant_id,
            'property_summary' => $summary,
            'guest_details' => $guest_details,
            'totals' => $totals,
            'payment_schedule' => $payment_schedule,
            'deposit_amount' => $deposit_amount,
            'payable_amount' => $payable_amount,
            'booking_record_id' => $booking_record_id > 0 ? $booking_record_id : 0,
            'created_at' => time(),
        ];

        set_transient($this->build_session_transient_key($session_token), $session_payload, $this->get_session_ttl());

        if ($booking_record_id > 0) {
            $this->booking_records->update_record(
                $booking_record_id,
                Property_Booking_Records::STATUS_READY_FOR_PAYMENT,
                [
                    'property_id' => $property_id,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'guests' => $guests,
                    'reztypeid' => $reztypeid,
                    'payment_mode' => $payment_mode,
                    'portal_id' => $portal_id,
                    'property_summary' => $summary,
                    'guest_details' => $guest_details,
                    'totals' => $totals,
                    'payment_schedule' => $payment_schedule,
                    'deposit_amount' => $deposit_amount,
                    'payable_amount' => $payable_amount,
                    'lease_id' => $lease_id,
                    'tenant_id' => $tenant_id,
                    'session_token_hash' => $this->build_session_token_hash($session_token),
                ],
                __('Checkout session is ready for payment.', 'barefoot-engine')
            );
        }

        return [
            'available' => true,
            'status' => 'ready',
            'sessionToken' => $session_token,
            'bookingRecordId' => $booking_record_id > 0 ? $booking_record_id : null,
            'propertySummary' => $summary,
            'totals' => $totals,
            'paymentSchedule' => $payment_schedule,
            'payableAmount' => $payable_amount,
            'depositAmount' => $deposit_amount,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|WP_Error
     */
    public function complete_session(array $payload): array|WP_Error
    {
        $session_token = sanitize_text_field((string) ($payload['session_token'] ?? ''));
        if ($session_token === '') {
            return new WP_Error(
                'barefoot_engine_checkout_missing_session',
                __('A valid checkout session is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $session = $this->load_session($session_token);
        if (is_wp_error($session)) {
            return $session;
        }

        $booking_record_id = isset($session['booking_record_id']) && is_numeric($session['booking_record_id'])
            ? (int) $session['booking_record_id']
            : 0;

        $property_id = $this->normalize_property_id((string) ($payload['property_id'] ?? ''));
        $check_in = $this->normalize_ymd_date((string) ($payload['check_in'] ?? ''));
        $check_out = $this->normalize_ymd_date((string) ($payload['check_out'] ?? ''));
        $guests = $this->normalize_guest_count($payload['guests'] ?? 1);

        if (
            $property_id !== (string) ($session['property_id'] ?? '')
            || $check_in !== (string) ($session['check_in'] ?? '')
            || $check_out !== (string) ($session['check_out'] ?? '')
            || $guests !== (int) ($session['guests'] ?? 0)
        ) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'session_mismatch' => true,
                        ],
                    ],
                    __('Checkout failed because stay details no longer match the active session.', 'barefoot-engine')
                );
            }

            return new WP_Error(
                'barefoot_engine_checkout_session_mismatch',
                __('The stay details changed. Please review the updated quote and continue again.', 'barefoot-engine'),
                ['status' => 409]
            );
        }

        $payment_details = $this->normalize_payment_details($payload['payment'] ?? []);
        if (is_wp_error($payment_details)) {
            return $payment_details;
        }

        $settings = $this->api_settings->get_settings();

        if ($this->is_mock_mode_enabled($settings)) {
            return $this->complete_mock_checkout_session($session_token, $session, $payment_details, $booking_record_id);
        }

        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_checkout_missing_credentials',
                __('Please save your Barefoot API credentials first.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $payable_amount = isset($session['payable_amount']) && is_numeric($session['payable_amount'])
            ? (float) $session['payable_amount']
            : 0.0;
        $deposit_amount = isset($session['deposit_amount']) && is_numeric($session['deposit_amount'])
            ? (float) $session['deposit_amount']
            : $payable_amount;
        $lease_id = isset($session['lease_id']) ? (int) $session['lease_id'] : 0;
        $tenant_id = isset($session['tenant_id']) ? (int) $session['tenant_id'] : 0;
        $payment_mode = (string) ($session['payment_mode'] ?? self::DEFAULT_PAYMENT_MODE);
        $portal_id = (string) ($session['portal_id'] ?? '');

        if ($lease_id <= 0 || $tenant_id <= 0) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_EXPIRED,
                    [
                        'diagnostics' => [
                            'invalid_session_payload' => true,
                        ],
                    ],
                    __('Checkout session became invalid before payment submission.', 'barefoot-engine')
                );
            }

            return new WP_Error(
                'barefoot_engine_checkout_invalid_session',
                __('The checkout session is missing required booking data. Please start again.', 'barefoot-engine'),
                ['status' => 410]
            );
        }

        $booking_response = $this->api_client->property_booking_new(
            $settings,
            $this->build_property_booking_payload(
                $session,
                $payment_details,
                $payment_mode,
                $payable_amount,
                $lease_id,
                $tenant_id
            ),
            $portal_id
        );
        if (is_wp_error($booking_response)) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'booking_error_code' => $booking_response->get_error_code(),
                            'booking_error_message' => $booking_response->get_error_message(),
                        ],
                    ],
                    __('Barefoot booking request failed.', 'barefoot-engine')
                );
            }

            return $booking_response;
        }

        $booking_result = $this->parser->parse_property_booking_result($booking_response);
        if (is_wp_error($booking_result)) {
            if ($booking_record_id > 0) {
                $this->booking_records->update_record(
                    $booking_record_id,
                    $booking_result->get_error_code() === 'barefoot_engine_checkout_property_invalidation'
                        ? Property_Booking_Records::STATUS_UNAVAILABLE
                        : Property_Booking_Records::STATUS_BOOKING_FAILED,
                    [
                        'diagnostics' => [
                            'booking_parse_error_code' => $booking_result->get_error_code(),
                            'booking_parse_error_message' => $booking_result->get_error_message(),
                        ],
                    ],
                    __('Barefoot booking response returned an error.', 'barefoot-engine')
                );
            }

            if ($booking_result->get_error_code() === 'barefoot_engine_checkout_property_invalidation') {
                delete_transient($this->build_session_transient_key($session_token));
            }

            return $booking_result;
        }

        $payment_summary = $this->build_masked_payment_summary($payment_details, $booking_result);
        $confirmation_token = '';
        $confirmation_url = '';
        $confirmation_token_hash = '';

        if ($booking_record_id > 0) {
            $confirmation_token = $this->generate_confirmation_token();
            $confirmation_token_hash = $this->build_confirmation_token_hash($confirmation_token);
            $confirmation_url = $this->get_confirmation_page_url($confirmation_token);
        }

        if ($booking_record_id > 0) {
            $this->booking_records->update_record(
                $booking_record_id,
                Property_Booking_Records::STATUS_BOOKING_SUCCESS,
                [
                    'folio_id' => (string) ($booking_result['folio_id'] ?? ''),
                    'amount' => isset($booking_result['amount']) && is_numeric($booking_result['amount'])
                        ? (float) $booking_result['amount']
                        : $payable_amount,
                    'payment_summary' => $payment_summary,
                    'totals' => isset($session['totals']) && is_array($session['totals']) ? $session['totals'] : [],
                    'payment_schedule' => isset($session['payment_schedule']) && is_array($session['payment_schedule']) ? $session['payment_schedule'] : [],
                    'deposit_amount' => $deposit_amount,
                    'payable_amount' => $payable_amount,
                    'lease_id' => $lease_id,
                    'tenant_id' => $tenant_id,
                    'confirmation_token_hash' => $confirmation_token_hash,
                    'diagnostics' => [
                        'booking_response' => 'received',
                    ],
                ],
                __('Booking completed successfully in Barefoot.', 'barefoot-engine')
            );
        }

        delete_transient($this->build_session_transient_key($session_token));

        return [
            'status' => 'success',
            'folioId' => (string) ($booking_result['folio_id'] ?? ''),
            'tenant' => (string) ($booking_result['tenant'] ?? ''),
            'amount' => isset($booking_result['amount']) && is_numeric($booking_result['amount'])
                ? $this->normalize_money((float) $booking_result['amount'])
                : $this->normalize_money($payable_amount),
            'creditCardNum' => $payment_summary['masked_card'] ?? '',
            'maskedCard' => $payment_summary['masked_card'] ?? '',
            'propertySummary' => $session['property_summary'] ?? [],
            'staySummary' => [
                'checkIn' => $session['check_in'] ?? '',
                'checkOut' => $session['check_out'] ?? '',
                'guests' => $session['guests'] ?? 1,
            ],
            'totals' => $session['totals'] ?? null,
            'paymentSchedule' => $session['payment_schedule'] ?? [],
            'payableAmount' => $this->normalize_money($payable_amount),
            'depositAmount' => $this->normalize_money($deposit_amount),
            'bookingRecordId' => $booking_record_id > 0 ? $booking_record_id : null,
            'confirmationToken' => $confirmation_token !== '' ? $confirmation_token : null,
            'confirmationUrl' => $confirmation_url,
        ];
    }

    private function find_property_post_id(string $property_id): int
    {
        $query = new \WP_Query(
            [
                'post_type' => Property_Post_Type::POST_TYPE,
                'post_status' => ['publish', 'private'],
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'meta_query' => [
                    [
                        'key' => '_be_property_id',
                        'value' => $property_id,
                        'compare' => '=',
                    ],
                ],
            ]
        );

        if (!is_array($query->posts) || $query->posts === []) {
            return 0;
        }

        return (int) $query->posts[0];
    }

    private function normalize_property_id(string $property_id): string
    {
        return trim(sanitize_text_field($property_id));
    }

    private function normalize_ymd_date(string $date): string
    {
        $normalized = trim($date);
        if ($normalized === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) !== 1) {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized, wp_timezone());
        if (!$parsed instanceof \DateTimeImmutable) {
            return '';
        }

        return $parsed->format('Y-m-d') === $normalized ? $normalized : '';
    }

    private function format_mdy_date(string $ymd): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, wp_timezone());
        if (!$date instanceof \DateTimeImmutable) {
            return '';
        }

        return $date->format('m/d/Y');
    }

    private function calculate_date_diff_days(string $start_date, string $end_date): int
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $start_date, wp_timezone());
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $end_date, wp_timezone());

        if (!$start instanceof \DateTimeImmutable || !$end instanceof \DateTimeImmutable) {
            return -1;
        }

        return (int) $start->diff($end)->days;
    }

    /**
     * @param mixed $value
     */
    private function normalize_guest_count($value): int
    {
        $numeric = is_numeric($value) ? (int) $value : 1;

        return max(1, min(99, $numeric));
    }

    /**
     * @param mixed $value
     */
    private function normalize_positive_int($value, int $default = 0): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : $default;
    }

    /**
     * @param mixed $value
     */
    private function normalize_guest_details($value): array|WP_Error
    {
        if (!is_array($value)) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_guest_details',
                __('Primary guest details are required to continue.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $normalized = [
            'first_name' => sanitize_text_field((string) ($value['first_name'] ?? '')),
            'last_name' => sanitize_text_field((string) ($value['last_name'] ?? '')),
            'email' => sanitize_email((string) ($value['email'] ?? '')),
            'cell_phone' => sanitize_text_field((string) ($value['cell_phone'] ?? '')),
            'address_1' => sanitize_text_field((string) ($value['address_1'] ?? '')),
            'address_2' => sanitize_text_field((string) ($value['address_2'] ?? '')),
            'city' => sanitize_text_field((string) ($value['city'] ?? '')),
            'state' => sanitize_text_field((string) ($value['state'] ?? '')),
            'country' => sanitize_text_field((string) ($value['country'] ?? '')),
            'postal_code' => sanitize_text_field((string) ($value['postal_code'] ?? '')),
            'age_confirmed' => !empty($value['age_confirmed']),
        ];

        $required_fields = [
            'first_name' => __('First name is required.', 'barefoot-engine'),
            'last_name' => __('Last name is required.', 'barefoot-engine'),
            'email' => __('A valid email address is required.', 'barefoot-engine'),
            'cell_phone' => __('Cell phone is required.', 'barefoot-engine'),
            'address_1' => __('Address 1 is required.', 'barefoot-engine'),
            'city' => __('City is required.', 'barefoot-engine'),
            'state' => __('State is required.', 'barefoot-engine'),
            'country' => __('Country is required.', 'barefoot-engine'),
            'postal_code' => __('Postal code is required.', 'barefoot-engine'),
        ];

        foreach ($required_fields as $key => $message) {
            if ($normalized[$key] === '') {
                return new WP_Error(
                    'barefoot_engine_checkout_invalid_guest_details',
                    $message,
                    ['status' => 400]
                );
            }
        }

        if ($normalized['age_confirmed'] !== true) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_guest_details',
                __('Please confirm the primary guest age requirement to continue.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function normalize_payment_details($value): array|WP_Error
    {
        if (!is_array($value)) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_payment_details',
                __('Payment details are required to complete checkout.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $card_number = preg_replace('/\D+/', '', (string) ($value['card_number'] ?? ''));
        $cvv = preg_replace('/\D+/', '', (string) ($value['cvv'] ?? ''));
        $month = preg_replace('/\D+/', '', (string) ($value['expiry_month'] ?? ''));
        $year = preg_replace('/\D+/', '', (string) ($value['expiry_year'] ?? ''));
        $name_on_card = sanitize_text_field((string) ($value['name_on_card'] ?? ''));

        if (!is_string($card_number) || strlen($card_number) < 12 || strlen($card_number) > 19) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_payment_details',
                __('A valid card number is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($name_on_card === '') {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_payment_details',
                __('Name on card is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if (!is_string($cvv) || strlen($cvv) < 3 || strlen($cvv) > 4) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_payment_details',
                __('A valid CVV is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $expiry_month = (int) $month;
        if ($expiry_month < 1 || $expiry_month > 12) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_payment_details',
                __('A valid expiration month is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $expiry_year = (int) $year;
        if ($expiry_year > 0 && strlen($year) === 2) {
            $expiry_year += 2000;
        }

        if ($expiry_year < (int) gmdate('Y') || $expiry_year > ((int) gmdate('Y') + 25)) {
            return new WP_Error(
                'barefoot_engine_checkout_invalid_payment_details',
                __('A valid expiration year is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return [
            'card_number' => $card_number,
            'cvv' => $cvv,
            'expiry_month' => str_pad((string) $expiry_month, 2, '0', STR_PAD_LEFT),
            'expiry_year' => (string) $expiry_year,
            'name_on_card' => $name_on_card,
        ];
    }

    private function normalize_payment_mode(string $value, string $fallback = self::DEFAULT_PAYMENT_MODE): string
    {
        $normalized = strtoupper(trim($value));

        return in_array($normalized, ['ON', 'TRUE', 'FALSE'], true)
            ? $normalized
            : $fallback;
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    private function resolve_payment_mode(mixed $value, ?array $settings = null): string
    {
        if (is_scalar($value)) {
            $raw = trim((string) $value);
            if ($raw !== '') {
                return $this->normalize_payment_mode($raw, $this->get_default_payment_mode($settings));
            }
        }

        return $this->get_default_payment_mode($settings);
    }

    /**
     * @param array<string, string> $guest_details
     * @return array<int, string>
     */
    private function build_consumer_info_payload(
        string $property_id,
        string $check_in,
        string $check_out,
        array $guest_details,
        string $source_of_business
    ): array {
        return [
            $guest_details['address_1'],
            $guest_details['address_2'],
            $guest_details['city'],
            $guest_details['state'],
            $guest_details['postal_code'],
            $guest_details['country'],
            $guest_details['last_name'],
            $guest_details['first_name'],
            '',
            '',
            '',
            $guest_details['cell_phone'],
            $guest_details['email'],
            $this->format_mdy_date($check_in),
            $this->format_mdy_date($check_out),
            $property_id,
            $source_of_business,
        ];
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, string> $payment_details
     * @return array<int, string>
     */
    private function build_property_booking_payload(
        array $session,
        array $payment_details,
        string $payment_mode,
        float $payable_amount,
        int $lease_id,
        int $tenant_id
    ): array {
        [$card_first_name, $card_last_name] = $this->split_name((string) $payment_details['name_on_card']);
        $card_type = $this->detect_card_type((string) $payment_details['card_number']);

        return [
            $payment_mode,
            $this->format_money_string($payable_amount),
            '',
            (string) ($session['property_id'] ?? ''),
            $this->format_mdy_date((string) ($session['check_in'] ?? '')),
            $this->format_mdy_date((string) ($session['check_out'] ?? '')),
            (string) $tenant_id,
            (string) $lease_id,
            '',
            $card_first_name,
            $card_last_name,
            '',
            'S',
            'C',
            (string) $payment_details['card_number'],
            (string) $payment_details['expiry_month'],
            (string) $payment_details['expiry_year'],
            (string) $payment_details['cvv'],
            'HOTEL',
            $card_type > 0 ? (string) $card_type : '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $line_items
     * @return array{daily_price: float, subtotal: float, tax_total: float, grand_total: float, nights: int}
     */
    private function calculate_quote_totals(array $line_items, int $nights): array
    {
        $subtotal = 0.0;
        $tax_total = 0.0;
        $grand_total = 0.0;
        $normalized_nights = max(1, $nights);

        foreach ($line_items as $line_item) {
            if (!is_array($line_item)) {
                continue;
            }

            $amount = isset($line_item['amount']) && is_numeric($line_item['amount'])
                ? (float) $line_item['amount']
                : null;

            if ($amount === null) {
                continue;
            }

            $name = isset($line_item['name']) ? strtolower(trim((string) $line_item['name'])) : '';

            if ($name !== '' && str_contains($name, 'rent')) {
                $subtotal += $amount;
            }

            if ($name !== '' && str_contains($name, 'tax')) {
                $tax_total += $amount;
            }

            if ($amount > 0) {
                $grand_total += $amount;
            }
        }

        return [
            'daily_price' => $this->normalize_money($subtotal / $normalized_nights),
            'subtotal' => $this->normalize_money($subtotal),
            'tax_total' => $this->normalize_money($tax_total),
            'grand_total' => $this->normalize_money($grand_total),
            'nights' => $normalized_nights,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $payment_schedule
     * @param array<string, float|int> $totals
     */
    private function resolve_deposit_amount(array $payment_schedule, array $totals): float
    {
        $row = $this->resolve_schedule_row(
            $payment_schedule,
            [
                'deposit',
                'down payment',
                'first payment',
                'first due',
                'due now',
                'initial payment',
            ]
        );

        if (is_array($row) && isset($row['amount']) && is_numeric($row['amount'])) {
            return $this->normalize_money(abs((float) $row['amount']));
        }

        return $this->resolve_payable_amount($payment_schedule, $totals, 0.0);
    }

    /**
     * @param array<int, array<string, mixed>> $payment_schedule
     * @param array<string, float|int> $totals
     */
    private function resolve_payable_amount(array $payment_schedule, array $totals, float $deposit_amount = 0.0): float
    {
        $row = $this->resolve_schedule_row(
            $payment_schedule,
            [
                'payable',
                'due now',
                'payment due',
                'first payment',
                'first due',
                'deposit',
            ]
        );

        if (is_array($row) && isset($row['amount']) && is_numeric($row['amount'])) {
            return $this->normalize_money(abs((float) $row['amount']));
        }

        if ($deposit_amount > 0) {
            return $this->normalize_money($deposit_amount);
        }

        return isset($totals['grand_total']) && is_numeric($totals['grand_total'])
            ? $this->normalize_money((float) $totals['grand_total'])
            : 0.0;
    }

    /**
     * @param array<int, array<string, mixed>> $payment_schedule
     * @param array<int, string> $preferred_labels
     * @return array<string, mixed>|null
     */
    private function resolve_schedule_row(array $payment_schedule, array $preferred_labels): ?array
    {
        $rows = [];

        foreach ($payment_schedule as $index => $row) {
            if (!is_array($row) || !isset($row['amount']) || !is_numeric($row['amount'])) {
                continue;
            }

            $amount = abs((float) $row['amount']);
            if ($amount <= 0) {
                continue;
            }

            $label = isset($row['label']) ? strtolower(trim((string) $row['label'])) : '';
            $due_date = isset($row['due_date']) ? trim((string) $row['due_date']) : '';

            $priority = 50;
            foreach ($preferred_labels as $position => $needle) {
                if ($label !== '' && str_contains($label, strtolower($needle))) {
                    $priority = $position;
                    break;
                }
            }

            $rows[] = [
                'row' => $row,
                'priority' => $priority,
                'due_date' => $due_date !== '' ? $due_date : '9999-12-31',
                'index' => (int) $index,
            ];
        }

        if ($rows === []) {
            return null;
        }

        usort(
            $rows,
            static function (array $left, array $right): int {
                if ($left['priority'] !== $right['priority']) {
                    return $left['priority'] <=> $right['priority'];
                }

                if ($left['due_date'] !== $right['due_date']) {
                    return strcmp((string) $left['due_date'], (string) $right['due_date']);
                }

                return $left['index'] <=> $right['index'];
            }
        );

        return $rows[0]['row'] ?? null;
    }

    private function resolve_property_title(array $fields, ?\WP_Post $post): string
    {
        $name = $this->clean_string($fields['name'] ?? '');
        $keyboard_id = $this->clean_string($fields['keyboardid'] ?? '');
        $unit_type = $this->resolve_unit_type($fields);
        $base_title = '';

        foreach ([$name, $keyboard_id, $post?->post_title] as $candidate) {
            $title = $this->clean_string($candidate);
            if ($title !== '') {
                $base_title = $title;
                break;
            }
        }

        if ($base_title !== '' && $unit_type !== '') {
            return $base_title . ' · ' . $unit_type;
        }

        foreach ([$base_title, $unit_type, $this->clean_string($fields['PropertyTitle'] ?? '')] as $candidate) {
            $title = $this->clean_string($candidate);
            if ($title !== '') {
                return $title;
            }
        }

        return __('Property', 'barefoot-engine');
    }

    private function resolve_property_address(array $fields): string
    {
        $city = $this->clean_string($fields['city'] ?? '');
        $state = $this->clean_string($fields['state'] ?? '');
        $zip = $this->clean_string($fields['zip'] ?? '');
        $country = $this->clean_string($fields['country'] ?? '');
        $locality_parts = [];

        if ($city !== '') {
            $locality_parts[] = $city;
        }

        $region = trim(implode(' ', array_filter([$state, $zip], static fn(string $value): bool => $value !== '')));
        if ($region !== '') {
            $locality_parts[] = $region;
        }

        if ($country !== '') {
            $locality_parts[] = $country;
        }

        if ($locality_parts !== []) {
            return implode(', ', $locality_parts);
        }

        foreach (['propAddressNew', 'propAddress', 'street', 'street2'] as $key) {
            $value = $this->clean_string($fields[$key] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function resolve_property_coordinates(array $fields): ?array
    {
        $latitude = $this->normalize_coordinate($fields['Latitude'] ?? null, -90, 90);
        $longitude = $this->normalize_coordinate($fields['Longitude'] ?? null, -180, 180);

        if ($latitude === null || $longitude === null) {
            return null;
        }

        if (abs($latitude) < 0.000001 && abs($longitude) < 0.000001) {
            return null;
        }

        return [
            'lat' => $latitude,
            'lng' => $longitude,
        ];
    }

    private function resolve_property_image_url(int $post_id, array $fields): string
    {
        $images = get_post_meta($post_id, '_be_property_images', true);
        if (is_array($images)) {
            foreach ($images as $image) {
                if (is_scalar($image)) {
                    $value = $this->clean_string((string) $image);
                    if ($value !== '') {
                        return esc_url_raw($value);
                    }
                }

                if (!is_array($image)) {
                    continue;
                }

                foreach (['url', 'image_url', 'ImagePath', 'imagepath'] as $key) {
                    $value = $this->clean_string($image[$key] ?? '');
                    if ($value !== '') {
                        return esc_url_raw($value);
                    }
                }
            }
        }

        $thumbnail_url = get_the_post_thumbnail_url($post_id, 'medium_large');
        if (is_string($thumbnail_url) && $thumbnail_url !== '') {
            return esc_url_raw($thumbnail_url);
        }

        foreach (['imagepath', 'PropertyUrls'] as $key) {
            $value = $this->clean_string($fields[$key] ?? '');
            if ($value !== '') {
                return esc_url_raw($value);
            }
        }

        return '';
    }

    private function normalize_coordinate(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        if ($number < $min || $number > $max) {
            return null;
        }

        return $number;
    }

    private function resolve_guest_count(int $post_id, array $fields): ?int
    {
        $value = get_post_meta($post_id, Property_Sync_Service::GUEST_COUNT_META_KEY, true);
        if (is_numeric($value)) {
            return max(1, (int) $value);
        }

        foreach (['a53', 'SleepsBeds', 'occupancy'] as $key) {
            if (!isset($fields[$key]) || !is_numeric($fields[$key])) {
                continue;
            }

            return max(1, (int) $fields[$key]);
        }

        return null;
    }

    private function resolve_bedroom_count(int $post_id, array $fields): ?int
    {
        $value = get_post_meta($post_id, Property_Sync_Service::BEDROOM_COUNT_META_KEY, true);
        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (isset($fields['a56']) && is_numeric($fields['a56'])) {
            return max(0, (int) $fields['a56']);
        }

        foreach (
            [
                $this->resolve_amenity_value($fields, ['bedrooms']),
                $this->resolve_unit_type($fields),
                $this->clean_string($fields['PropertyTitle'] ?? ''),
            ] as $candidate
        ) {
            $parsed = $this->parse_count_from_text($candidate, ['bedroom', 'bedrooms', 'bed', 'beds']);
            if ($parsed !== null) {
                return (int) $parsed;
            }
        }

        return null;
    }

    private function resolve_bathroom_count(int $post_id, array $fields): ?string
    {
        $value = get_post_meta($post_id, Property_Sync_Service::BATHROOM_COUNT_META_KEY, true);
        if (is_scalar($value)) {
            $normalized = trim((string) $value);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        if (isset($fields['a195']) && is_scalar($fields['a195'])) {
            $normalized = trim((string) $fields['a195']);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        foreach (
            [
                $this->resolve_amenity_value($fields, ['a195', 'bathrooms', 'number-of-bathrooms']),
                $this->resolve_unit_type($fields),
                $this->clean_string($fields['PropertyTitle'] ?? ''),
            ] as $candidate
        ) {
            $parsed = $this->parse_count_from_text($candidate, ['bathroom', 'bathrooms', 'bath', 'baths', 'ba']);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    private function resolve_unit_type(array $fields): string
    {
        $direct_value = $this->clean_string($fields['a259'] ?? '');
        if ($direct_value !== '') {
            return $direct_value;
        }

        $amenity_value = $this->resolve_amenity_value($fields, ['unit-type', 'Unit Type']);
        if ($amenity_value !== null) {
            return $amenity_value;
        }

        return '';
    }

    /**
     * @param array<int, string> $candidate_keys
     */
    private function resolve_amenity_value(array $fields, array $candidate_keys): ?string
    {
        if (!isset($fields['amenities']) || !is_array($fields['amenities'])) {
            return null;
        }

        $normalized_candidates = array_filter(
            array_map(
                static fn(string $candidate): string => strtolower(trim($candidate)),
                $candidate_keys
            ),
            static fn(string $candidate): bool => $candidate !== ''
        );

        if ($normalized_candidates === []) {
            return null;
        }

        foreach ($fields['amenities'] as $amenity) {
            if (!is_array($amenity)) {
                continue;
            }

            $amenity_keys = [
                strtolower(trim((string) ($amenity['key'] ?? ''))),
                strtolower(trim((string) ($amenity['label'] ?? ''))),
                strtolower(trim((string) ($amenity['display'] ?? ''))),
            ];

            if (array_intersect($normalized_candidates, array_filter($amenity_keys)) === []) {
                continue;
            }

            $value = $this->clean_string($amenity['value'] ?? '');
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $tokens
     */
    private function parse_count_from_text(mixed $value, array $tokens): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $token_pattern = implode('|', array_map(static fn(string $token): string => preg_quote($token, '/'), $tokens));
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:' . $token_pattern . ')\b/i', $text, $matches) !== 1) {
            return null;
        }

        return $this->normalize_non_negative_number_string($matches[1] ?? null);
    }

    private function normalize_non_negative_number_string(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;
        if ($numeric < 0) {
            return null;
        }

        if (abs($numeric - round($numeric)) < 0.00001) {
            return (string) (int) round($numeric);
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    private function clean_string(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function generate_session_token(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return wp_generate_password(32, false, false);
        }
    }

    private function build_session_transient_key(string $session_token): string
    {
        return self::SESSION_TRANSIENT_PREFIX . hash('sha256', $session_token);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function load_session_if_exists(string $session_token): ?array
    {
        if ($session_token === '') {
            return null;
        }

        $session = get_transient($this->build_session_transient_key($session_token));

        return is_array($session) ? $session : null;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function load_session(string $session_token): array|WP_Error
    {
        $session = get_transient($this->build_session_transient_key($session_token));
        if (!is_array($session)) {
            return new WP_Error(
                'barefoot_engine_checkout_session_expired',
                __('This checkout session has expired. Please review your stay details and continue again.', 'barefoot-engine'),
                ['status' => 410]
            );
        }

        return $session;
    }

    private function get_session_ttl(): int
    {
        return max(
            300,
            (int) apply_filters('barefoot_engine_booking_checkout_session_ttl', self::DEFAULT_SESSION_TTL)
        );
    }

    private function build_session_token_hash(string $session_token): string
    {
        if ($session_token === '') {
            return '';
        }

        return hash('sha256', $session_token);
    }

    private function is_mock_mode_enabled(?array $settings = null): bool
    {
        $enabled = $this->api_settings->is_booking_mock_mode_enabled($settings);

        return (bool) apply_filters('barefoot_engine_booking_checkout_mock_mode', $enabled, $settings);
    }

    /**
     * @param array<string, mixed>|null $settings
     */
    private function get_default_payment_mode(?array $settings = null): string
    {
        return $this->normalize_payment_mode(
            $this->api_settings->get_booking_payment_mode($settings),
            self::DEFAULT_PAYMENT_MODE
        );
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, string> $guest_details
     * @param array<string, mixed> $quote_payload
     * @return array<string, mixed>
     */
    private function create_mock_checkout_session(
        string $session_token,
        int $booking_record_id,
        string $property_id,
        string $check_in,
        string $check_out,
        int $guests,
        int $reztypeid,
        string $payment_mode,
        string $portal_id,
        string $source_of_business,
        array $summary,
        array $guest_details,
        array $quote_payload
    ): array {
        $totals = $this->extract_mock_totals($quote_payload, $check_in, $check_out);
        $deposit_amount = $this->resolve_mock_deposit_amount($quote_payload, $totals);
        $payable_amount = $this->resolve_mock_payable_amount($quote_payload, $totals, $deposit_amount);
        $payment_schedule = [
            [
                'label' => __('Mock payment due now', 'barefoot-engine'),
                'amount' => $this->format_money_string($deposit_amount),
                'due_date' => $check_in,
            ],
        ];
        $lease_id = 900000 + wp_rand(1000, 9999);
        $tenant_id = 700000 + wp_rand(1000, 9999);

        $session_payload = [
            'property_id' => $property_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'guests' => $guests,
            'reztypeid' => $reztypeid,
            'payment_mode' => $payment_mode,
            'portal_id' => $portal_id,
            'source_of_business' => $source_of_business,
            'lease_id' => $lease_id,
            'tenant_id' => $tenant_id,
            'property_summary' => $summary,
            'guest_details' => $guest_details,
            'totals' => $totals,
            'payment_schedule' => $payment_schedule,
            'deposit_amount' => $deposit_amount,
            'payable_amount' => $payable_amount,
            'booking_record_id' => $booking_record_id > 0 ? $booking_record_id : 0,
            'mock_mode' => true,
            'created_at' => time(),
        ];

        set_transient($this->build_session_transient_key($session_token), $session_payload, $this->get_session_ttl());

        if ($booking_record_id > 0) {
            $this->booking_records->update_record(
                $booking_record_id,
                Property_Booking_Records::STATUS_READY_FOR_PAYMENT,
                [
                    'property_id' => $property_id,
                    'check_in' => $check_in,
                    'check_out' => $check_out,
                    'guests' => $guests,
                    'reztypeid' => $reztypeid,
                    'payment_mode' => $payment_mode,
                    'portal_id' => $portal_id,
                    'property_summary' => $summary,
                    'guest_details' => $guest_details,
                    'totals' => $totals,
                    'payment_schedule' => $payment_schedule,
                    'deposit_amount' => $deposit_amount,
                    'payable_amount' => $payable_amount,
                    'lease_id' => $lease_id,
                    'tenant_id' => $tenant_id,
                    'session_token_hash' => $this->build_session_token_hash($session_token),
                    'diagnostics' => [
                        'mock_mode' => true,
                        'mock_checkout_session' => true,
                    ],
                ],
                __('Mock checkout session is ready for payment.', 'barefoot-engine')
            );
        }

        return [
            'available' => true,
            'status' => 'ready',
            'sessionToken' => $session_token,
            'bookingRecordId' => $booking_record_id > 0 ? $booking_record_id : null,
            'propertySummary' => $summary,
            'totals' => $totals,
            'paymentSchedule' => $payment_schedule,
            'payableAmount' => $payable_amount,
            'depositAmount' => $deposit_amount,
            'mockMode' => true,
        ];
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, string> $payment_details
     * @return array<string, mixed>
     */
    private function complete_mock_checkout_session(
        string $session_token,
        array $session,
        array $payment_details,
        int $booking_record_id
    ): array {
        $payable_amount = isset($session['payable_amount']) && is_numeric($session['payable_amount'])
            ? (float) $session['payable_amount']
            : 0.0;
        $deposit_amount = isset($session['deposit_amount']) && is_numeric($session['deposit_amount'])
            ? (float) $session['deposit_amount']
            : $payable_amount;
        $folio_id = 'MOCK-' . strtoupper(wp_generate_password(8, false, false));
        $booking_result = [
            'folio_id' => $folio_id,
            'tenant' => isset($session['tenant_id']) ? (string) $session['tenant_id'] : '',
            'amount' => $this->normalize_money($payable_amount),
        ];
        $payment_summary = $this->build_masked_payment_summary($payment_details, $booking_result);
        $confirmation_token = '';
        $confirmation_url = '';
        $confirmation_token_hash = '';

        if ($booking_record_id > 0) {
            $confirmation_token = $this->generate_confirmation_token();
            $confirmation_token_hash = $this->build_confirmation_token_hash($confirmation_token);
            $confirmation_url = $this->get_confirmation_page_url($confirmation_token);
        }

        if ($booking_record_id > 0) {
            $this->booking_records->update_record(
                $booking_record_id,
                Property_Booking_Records::STATUS_BOOKING_SUCCESS,
                [
                    'folio_id' => $folio_id,
                    'amount' => $this->normalize_money($payable_amount),
                    'payment_summary' => $payment_summary,
                    'totals' => isset($session['totals']) && is_array($session['totals']) ? $session['totals'] : [],
                    'payment_schedule' => isset($session['payment_schedule']) && is_array($session['payment_schedule']) ? $session['payment_schedule'] : [],
                    'deposit_amount' => $deposit_amount,
                    'payable_amount' => $payable_amount,
                    'lease_id' => isset($session['lease_id']) ? (int) $session['lease_id'] : 0,
                    'tenant_id' => isset($session['tenant_id']) ? (int) $session['tenant_id'] : 0,
                    'confirmation_token_hash' => $confirmation_token_hash,
                    'diagnostics' => [
                        'mock_mode' => true,
                        'mock_booking_completion' => true,
                    ],
                ],
                __('Mock booking completed locally.', 'barefoot-engine')
            );
        }

        delete_transient($this->build_session_transient_key($session_token));

        return [
            'status' => 'success',
            'folioId' => $folio_id,
            'tenant' => isset($session['tenant_id']) ? (string) $session['tenant_id'] : '',
            'amount' => $this->normalize_money($payable_amount),
            'creditCardNum' => $payment_summary['masked_card'] ?? '',
            'maskedCard' => $payment_summary['masked_card'] ?? '',
            'propertySummary' => $session['property_summary'] ?? [],
            'staySummary' => [
                'checkIn' => $session['check_in'] ?? '',
                'checkOut' => $session['check_out'] ?? '',
                'guests' => $session['guests'] ?? 1,
            ],
            'totals' => $session['totals'] ?? null,
            'paymentSchedule' => $session['payment_schedule'] ?? [],
            'payableAmount' => $this->normalize_money($payable_amount),
            'depositAmount' => $this->normalize_money($deposit_amount),
            'bookingRecordId' => $booking_record_id > 0 ? $booking_record_id : null,
            'confirmationToken' => $confirmation_token !== '' ? $confirmation_token : null,
            'confirmationUrl' => $confirmation_url,
            'mockMode' => true,
        ];
    }

    /**
     * @param array<string, mixed> $quote_payload
     * @return array{daily_price: float, subtotal: float, tax_total: float, grand_total: float, nights: int}
     */
    private function extract_mock_totals(array $quote_payload, string $check_in, string $check_out): array
    {
        $nights = max(1, $this->calculate_date_diff_days($check_in, $check_out));
        $totals = isset($quote_payload['totals']) && is_array($quote_payload['totals'])
            ? $quote_payload['totals']
            : [];

        $subtotal = isset($totals['subtotal']) && is_numeric($totals['subtotal'])
            ? $this->normalize_money((float) $totals['subtotal'])
            : 0.0;
        $tax_total = isset($totals['tax_total']) && is_numeric($totals['tax_total'])
            ? $this->normalize_money((float) $totals['tax_total'])
            : 0.0;
        $grand_total = isset($totals['grand_total']) && is_numeric($totals['grand_total'])
            ? $this->normalize_money((float) $totals['grand_total'])
            : $this->normalize_money($subtotal + $tax_total);
        $daily_price = isset($totals['daily_price']) && is_numeric($totals['daily_price'])
            ? $this->normalize_money((float) $totals['daily_price'])
            : ($nights > 0 ? $this->normalize_money($subtotal / $nights) : 0.0);

        return [
            'daily_price' => $daily_price,
            'subtotal' => $subtotal,
            'tax_total' => $tax_total,
            'grand_total' => $grand_total,
            'nights' => $nights,
        ];
    }

    /**
     * @param array<string, mixed> $quote_payload
     * @param array<string, float|int> $totals
     */
    private function resolve_mock_deposit_amount(array $quote_payload, array $totals): float
    {
        if (isset($quote_payload['depositAmount']) && is_numeric($quote_payload['depositAmount'])) {
            return $this->normalize_money((float) $quote_payload['depositAmount']);
        }

        if (isset($quote_payload['deposit_amount']) && is_numeric($quote_payload['deposit_amount'])) {
            return $this->normalize_money((float) $quote_payload['deposit_amount']);
        }

        if (isset($quote_payload['payableAmount']) && is_numeric($quote_payload['payableAmount'])) {
            return $this->normalize_money((float) $quote_payload['payableAmount']);
        }

        return isset($totals['grand_total']) && is_numeric($totals['grand_total'])
            ? $this->normalize_money((float) $totals['grand_total'])
            : 0.0;
    }

    /**
     * @param array<string, mixed> $quote_payload
     * @param array<string, float|int> $totals
     */
    private function resolve_mock_payable_amount(array $quote_payload, array $totals, float $deposit_amount = 0.0): float
    {
        if (isset($quote_payload['payableAmount']) && is_numeric($quote_payload['payableAmount'])) {
            return $this->normalize_money((float) $quote_payload['payableAmount']);
        }

        if (isset($quote_payload['payable_amount']) && is_numeric($quote_payload['payable_amount'])) {
            return $this->normalize_money((float) $quote_payload['payable_amount']);
        }

        if (isset($quote_payload['depositAmount']) && is_numeric($quote_payload['depositAmount'])) {
            return $this->normalize_money((float) $quote_payload['depositAmount']);
        }

        if (isset($quote_payload['deposit_amount']) && is_numeric($quote_payload['deposit_amount'])) {
            return $this->normalize_money((float) $quote_payload['deposit_amount']);
        }

        if ($deposit_amount > 0) {
            return $this->normalize_money($deposit_amount);
        }

        return isset($totals['grand_total']) && is_numeric($totals['grand_total'])
            ? $this->normalize_money((float) $totals['grand_total'])
            : 0.0;
    }

    private function normalize_money(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }

    private function format_money_string(float $value): string
    {
        return number_format($this->normalize_money($value), 2, '.', '');
    }

    private function resolve_redirect_url(
        string $redirect_url,
        string $property_id,
        string $check_in,
        string $check_out,
        int $guests,
        int $reztypeid
    ): string {
        $fallback_path = '/booking-confirmation';
        $raw_target = trim($redirect_url);
        if ($raw_target === '') {
            $raw_target = $fallback_path;
        }

        if (str_starts_with($raw_target, '/')) {
            $target = home_url($raw_target);
        } else {
            $target = $raw_target;
        }

        $sanitized_target = wp_validate_redirect($target, home_url($fallback_path));
        $parts = wp_parse_url($sanitized_target);
        if (!is_array($parts)) {
            $sanitized_target = home_url($fallback_path);
            $parts = wp_parse_url($sanitized_target);
        }

        $query = [];
        if (is_array($parts) && isset($parts['query']) && is_string($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['property_id'] = $property_id;
        $query['check_in'] = $check_in;
        $query['check_out'] = $check_out;
        $query['guests'] = (string) $guests;
        $query['reztypeid'] = (string) $reztypeid;

        return add_query_arg($query, $sanitized_target);
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function resolve_confirmation_property_summary(array $record): array
    {
        $summary = is_array($record['property_summary'] ?? null) ? $record['property_summary'] : [];
        $property_id = $this->clean_string($record['property_id'] ?? '');

        if ($property_id !== '') {
            $fresh_summary = $this->get_property_summary($property_id);
            if (is_array($fresh_summary)) {
                foreach (['postId', 'propertyId', 'title', 'address', 'imageUrl', 'permalink'] as $key) {
                    $summary_value = $summary[$key] ?? null;
                    $clean_summary_value = is_scalar($summary_value) ? $this->clean_string((string) $summary_value) : '';
                    if ($clean_summary_value === '' && array_key_exists($key, $fresh_summary)) {
                        $summary[$key] = $fresh_summary[$key];
                    }
                }

                if (!is_array($summary['stats'] ?? null) || $summary['stats'] === []) {
                    $summary['stats'] = $fresh_summary['stats'] ?? [];
                }

                if (!is_array($summary['coordinates'] ?? null) || $summary['coordinates'] === []) {
                    $summary['coordinates'] = $fresh_summary['coordinates'] ?? null;
                }
            }
        }

        if (!is_array($summary['stats'] ?? null)) {
            $summary['stats'] = [];
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $property_summary
     * @return array{lat: float, lng: float}|null
     */
    private function extract_confirmation_coordinates(array $property_summary): ?array
    {
        $coordinates = $property_summary['coordinates'] ?? null;
        if (is_array($coordinates) && isset($coordinates['lat'], $coordinates['lng'])) {
            $latitude = $this->normalize_coordinate($coordinates['lat'], -90, 90);
            $longitude = $this->normalize_coordinate($coordinates['lng'], -180, 180);
            if ($latitude !== null && $longitude !== null) {
                return [
                    'lat' => $latitude,
                    'lng' => $longitude,
                ];
            }
        }

        $post_id = isset($property_summary['postId']) && is_numeric($property_summary['postId'])
            ? (int) $property_summary['postId']
            : 0;
        if ($post_id <= 0) {
            return null;
        }

        $fields = get_post_meta($post_id, '_be_property_fields', true);
        if (!is_array($fields)) {
            return null;
        }

        return $this->resolve_property_coordinates($fields);
    }

    private function generate_confirmation_token(): string
    {
        return wp_generate_password(48, false, false);
    }

    private function build_confirmation_token_hash(string $confirmation_token): string
    {
        return hash('sha256', $this->clean_string($confirmation_token));
    }

    private function format_confirmation_display_date(string $ymd): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, wp_timezone());
        if (!$date instanceof \DateTimeImmutable) {
            return '—';
        }

        return wp_date('M j, Y', $date->getTimestamp(), wp_timezone());
    }

    private function build_confirmation_map_embed_url(float $latitude, float $longitude): string
    {
        $delta = 0.0125;
        $bbox = [
            $longitude - $delta,
            $latitude - $delta,
            $longitude + $delta,
            $latitude + $delta,
        ];

        return add_query_arg(
            [
                'bbox' => implode(',', array_map(static fn(float $value): string => number_format($value, 6, '.', ''), $bbox)),
                'layer' => 'mapnik',
                'marker' => number_format($latitude, 6, '.', '') . ',' . number_format($longitude, 6, '.', ''),
            ],
            'https://www.openstreetmap.org/export/embed.html'
        );
    }

    /**
     * @return array<string, string>
     */
    private function build_confirmation_calendar_links(
        string $confirmation_token,
        string $property_title,
        string $property_address,
        string $check_in,
        string $check_out,
        string $folio_id
    ): array {
        $summary = sprintf(
            /* translators: %s is the property title. */
            __('Stay at %s', 'barefoot-engine'),
            $property_title
        );
        $description = $folio_id !== ''
            ? sprintf(
                /* translators: %s is the folio id. */
                __('Reservation ID: %s', 'barefoot-engine'),
                $folio_id
            )
            : __('Booking confirmation', 'barefoot-engine');
        $google_dates = str_replace('-', '', $check_in) . '/' . str_replace('-', '', $check_out);
        $ics_url = add_query_arg(
            ['confirmation' => sanitize_text_field($confirmation_token)],
            home_url(user_trailingslashit(trim(self::DEFAULT_CONFIRMATION_ICS_PATH, '/')))
        );
        $google_url = add_query_arg(
            [
                'action' => 'TEMPLATE',
                'text' => $summary,
                'dates' => $google_dates,
                'details' => $description,
                'location' => $property_address,
            ],
            'https://calendar.google.com/calendar/render'
        );
        $outlook_url = add_query_arg(
            [
                'path' => '/calendar/action/compose',
                'rru' => 'addevent',
                'allday' => 'true',
                'subject' => $summary,
                'startdt' => $check_in,
                'enddt' => $check_out,
                'body' => $description,
                'location' => $property_address,
            ],
            'https://outlook.live.com/calendar/0/deeplink/compose'
        );

        return [
            'icsUrl' => $ics_url,
            'googleUrl' => $google_url,
            'outlookUrl' => $outlook_url,
        ];
    }

    /**
     * @param array<string, string> $event
     */
    private function build_ics_content(array $event): string
    {
        $created_at = gmdate('Ymd\THis\Z');
        $start = str_replace('-', '', $this->clean_string($event['check_in'] ?? ''));
        $end = str_replace('-', '', $this->clean_string($event['check_out'] ?? ''));
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Barefoot Engine//Booking Confirmation//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $this->escape_ics_text($this->clean_string($event['uid'] ?? '')),
            'DTSTAMP:' . $created_at,
            'DTSTART;VALUE=DATE:' . $start,
            'DTEND;VALUE=DATE:' . $end,
            'SUMMARY:' . $this->escape_ics_text($this->clean_string($event['title'] ?? '')),
            'DESCRIPTION:' . $this->escape_ics_text($this->clean_string($event['description'] ?? '')),
            'LOCATION:' . $this->escape_ics_text($this->clean_string($event['location'] ?? '')),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape_ics_text(string $value): string
    {
        return str_replace(
            ["\\", ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\;', '\,', '\n', '\n', '\n'],
            $value
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split_name(string $full_name): array
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $full_name) ?? '');
        if ($normalized === '') {
            return ['', ''];
        }

        $parts = explode(' ', $normalized, 2);
        if (count($parts) === 1) {
            return [$parts[0], ''];
        }

        return [$parts[0], $parts[1]];
    }

    private function detect_card_type(string $card_number): int
    {
        if (preg_match('/^5[1-5][0-9]{14}$/', $card_number) === 1 || preg_match('/^2(2[2-9]|[3-7][0-9])[0-9]{12}$/', $card_number) === 1) {
            return 1;
        }

        if (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $card_number) === 1) {
            return 2;
        }

        if (preg_match('/^6(?:011|5[0-9]{2})[0-9]{12}$/', $card_number) === 1) {
            return 3;
        }

        if (preg_match('/^3[47][0-9]{13}$/', $card_number) === 1) {
            return 4;
        }

        return 0;
    }

    /**
     * @param array<string, string> $payment_details
     * @param array<string, mixed> $booking_result
     * @return array<string, string>
     */
    private function build_masked_payment_summary(array $payment_details, array $booking_result): array
    {
        $card_number = isset($payment_details['card_number']) ? preg_replace('/\D+/', '', (string) $payment_details['card_number']) : '';
        $last4 = is_string($card_number) && strlen($card_number) >= 4
            ? substr($card_number, -4)
            : '';
        $card_type = $this->detect_card_type((string) $card_number);
        $gateway_card_value = $this->clean_string($booking_result['credit_card_num'] ?? '');
        $masked_gateway_value = $this->mask_card_number_for_display($gateway_card_value);

        if ($masked_gateway_value === '' && $last4 !== '') {
            $masked_gateway_value = '**** **** **** ' . $last4;
        }

        return [
            'card_type' => $this->card_type_label($card_type),
            'last4' => $last4 !== '' ? $last4 : '****',
            'masked_card' => $masked_gateway_value,
            'name_on_card' => $this->clean_string($payment_details['name_on_card'] ?? ''),
        ];
    }

    private function mask_card_number_for_display(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        if (strlen($digits) <= 4) {
            return '****';
        }

        return '**** **** **** ' . substr($digits, -4);
    }

    private function card_type_label(int $card_type): string
    {
        return match ($card_type) {
            1 => 'Mastercard',
            2 => 'Visa',
            3 => 'Discover',
            4 => 'American Express',
            default => 'Card',
        };
    }
}
