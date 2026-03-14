<?php

namespace BarefootEngine\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Barefoot_Api_Client
{
    private const DEFAULT_BASE_URL = 'https://portals.barefoot.com/barefootwebservice/barefootservice.asmx';
    private const TEST_METHOD = 'GetPropertyAmmenityNameXML';
    private const TEST_AMENITY_ID = '53';

    /**
     * @param array<string, array<string, string>> $settings
     * @return array<string, int|string>|WP_Error
     */
    public function test_connection(array $settings)
    {
        $result = $this->request_xml_string_method(
            self::TEST_METHOD,
            $settings,
            [
                'num' => self::TEST_AMENITY_ID,
            ]
        );

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === '') {
            return new WP_Error(
                'barefoot_engine_api_empty_response',
                __('Barefoot returned an empty response during the connection test.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        return [
            'checked_at' => time(),
            'endpoint' => $this->build_method_url(self::TEST_METHOD),
            'method' => self::TEST_METHOD,
            'remote_status' => 200,
        ];
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_property_ext_xml(array $settings): string|WP_Error
    {
        return $this->request_xml_string_method('GetPropertyExt', $settings);
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_last_updated_property_ids_string(array $settings, string $last_access_time): string|WP_Error
    {
        $normalized_last_access_time = trim($last_access_time);

        return $this->request_xml_string_method(
            'GetLastUpdatedPropertyIDs',
            $settings,
            [
                'lastaccesstime' => $normalized_last_access_time,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_property_details_xml(array $settings, string $property_id): string|WP_Error
    {
        $normalized_property_id = trim($property_id);
        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('A Barefoot Property ID is required to fetch property details.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_xml_string_method(
            'GetPropertyDetails',
            $settings,
            [
                'PropertyID' => $normalized_property_id,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_property_images_xml(array $settings, string $property_id): string|WP_Error
    {
        $normalized_property_id = trim($property_id);
        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('A Barefoot Property ID is required to fetch property images.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_xml_string_method(
            'GetPropertyAllImgs',
            $settings,
            [
                'propertyId' => $normalized_property_id,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_property_rates_xml(array $settings, string $property_id, string $start_date, string $end_date): string|WP_Error
    {
        $normalized_property_id = trim($property_id);
        $normalized_start_date = trim($start_date);
        $normalized_end_date = trim($end_date);

        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('A Barefoot Property ID is required to fetch property rates.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($normalized_start_date === '' || $normalized_end_date === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_rate_window',
                __('A valid rate date window is required to fetch property rates.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_xml_document_method(
            'GetPropertyRates',
            $settings,
            [
                'propertyId' => $normalized_property_id,
                'Date1' => $normalized_start_date,
                'Date2' => $normalized_end_date,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_property_booking_date_xml(
        array $settings,
        string $property_id,
        string $date1,
        string $date2
    ): string|WP_Error {
        $normalized_property_id = trim($property_id);
        $normalized_date1 = trim($date1);
        $normalized_date2 = trim($date2);

        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('A Barefoot Property ID is required to fetch blocked booking dates.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($normalized_date1 === '' || $normalized_date2 === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_booking_window',
                __('A valid booking date window is required to fetch blocked booking dates.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_xml_document_method(
            'GetPropertyBookingDate',
            $settings,
            [
                'propertyId' => $normalized_property_id,
                'date1' => $normalized_date1,
                'date2' => $normalized_date2,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return bool|WP_Error
     */
    public function is_property_availability(
        array $settings,
        string $property_id,
        string $date1,
        string $date2
    ): bool|WP_Error {
        $normalized_property_id = trim($property_id);
        $normalized_date1 = trim($date1);
        $normalized_date2 = trim($date2);

        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('A Barefoot Property ID is required to check availability.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($normalized_date1 === '' || $normalized_date2 === '') {
            return new WP_Error(
                'barefoot_engine_availability_missing_dates',
                __('A valid date range is required to check availability.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $response = $this->request_method(
            'IsPropertyAvailability',
            $settings,
            [
                'propertyId' => $normalized_property_id,
                'date1' => $normalized_date1,
                'date2' => $normalized_date2,
            ]
        );
        if (is_wp_error($response)) {
            return $response;
        }

        $body = isset($response['body']) && is_string($response['body']) ? trim($response['body']) : '';
        if ($body === '') {
            return new WP_Error(
                'barefoot_engine_api_empty_response',
                __('Barefoot returned an empty response while checking availability.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $parsed = $this->parse_boolean_response($body);
        if ($parsed === null) {
            return new WP_Error(
                'barefoot_engine_api_invalid_response',
                __('Barefoot returned an invalid availability response.', 'barefoot-engine'),
                [
                    'status' => 502,
                    'details' => $this->summarize_remote_error($body),
                ]
            );
        }

        return $parsed;
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_quote_rates_detail_string(
        array $settings,
        string $property_id,
        string $arrival_date,
        string $departure_date,
        int $num_adult,
        int $num_pet,
        int $num_baby,
        int $num_child,
        int $reztypeid
    ): string|WP_Error {
        $normalized_property_id = trim($property_id);
        $normalized_arrival_date = trim($arrival_date);
        $normalized_departure_date = trim($departure_date);

        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('A Barefoot Property ID is required to fetch quote rates.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($normalized_arrival_date === '' || $normalized_departure_date === '') {
            return new WP_Error(
                'barefoot_engine_quote_missing_dates',
                __('A valid quote date range is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($reztypeid <= 0) {
            return new WP_Error(
                'barefoot_engine_quote_missing_reztypeid',
                __('A valid reservation type ID is required to fetch quote rates.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_xml_string_method(
            'GetQuoteRatesDetail',
            $settings,
            [
                'propertyId' => $normalized_property_id,
                'strADate' => $normalized_arrival_date,
                'strDDate' => $normalized_departure_date,
                'num_adult' => (string) max(0, $num_adult),
                'num_pet' => (string) max(0, $num_pet),
                'num_baby' => (string) max(0, $num_baby),
                'num_child' => (string) max(0, $num_child),
                'reztypeid' => (string) $reztypeid,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function create_quote_and_get_payment_schedule_string(
        array $settings,
        string $property_id,
        string $arrival_date,
        string $departure_date,
        int $num_adult,
        int $num_pet,
        int $num_baby,
        int $num_child,
        int $reztypeid
    ): string|WP_Error {
        $normalized_property_id = trim($property_id);
        $normalized_arrival_date = trim($arrival_date);
        $normalized_departure_date = trim($departure_date);

        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_property_missing_id',
                __('A Barefoot Property ID is required to create a checkout quote.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($normalized_arrival_date === '' || $normalized_departure_date === '') {
            return new WP_Error(
                'barefoot_engine_quote_missing_dates',
                __('A valid quote date range is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($reztypeid <= 0) {
            return new WP_Error(
                'barefoot_engine_quote_missing_reztypeid',
                __('A valid reservation type ID is required to create a checkout quote.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_xml_string_method(
            'CreateQuoteAndGetPaymentSchedule',
            $settings,
            [
                'propertyId' => $normalized_property_id,
                'strADate' => $normalized_arrival_date,
                'strDDate' => $normalized_departure_date,
                'num_adult' => (string) max(0, $num_adult),
                'num_pet' => (string) max(0, $num_pet),
                'num_baby' => (string) max(0, $num_baby),
                'num_child' => (string) max(0, $num_child),
                'reztypeid' => (string) $reztypeid,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @param array<int, string> $info
     * @return string|WP_Error
     */
    public function set_consumer_info(array $settings, array $info): string|WP_Error
    {
        if ($info === []) {
            return new WP_Error(
                'barefoot_engine_checkout_missing_guest_info',
                __('Guest information is required to create a checkout session.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_scalar_method(
            'SetConsumerInfo',
            $settings,
            [
                'Info' => array_values($info),
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @param array<int, string> $info
     * @return string|WP_Error
     */
    public function property_booking_new(array $settings, array $info, string $portal_id = ''): string|WP_Error
    {
        if ($info === []) {
            return new WP_Error(
                'barefoot_engine_checkout_missing_payment_info',
                __('Payment information is required to complete checkout.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_scalar_method(
            'PropertyBookingNew',
            $settings,
            [
                'Info' => array_values($info),
                'portalid' => trim($portal_id),
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_property_availability_by_date_xml(
        array $settings,
        string $date1,
        string $date2,
        int $weekly = 0
    ): string|WP_Error {
        $normalized_date1 = trim($date1);
        $normalized_date2 = trim($date2);

        if ($normalized_date1 === '' || $normalized_date2 === '') {
            return new WP_Error(
                'barefoot_engine_availability_missing_dates',
                __('A valid date range is required to fetch property availability.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        return $this->request_xml_string_method(
            'GetPropertyAvailabilityByDateXML',
            $settings,
            [
                'date1' => $normalized_date1,
                'date2' => $normalized_date2,
                'weekly' => (string) $weekly,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_last_avail_changed_properties_string(array $settings, string $last_access): string|WP_Error
    {
        $normalized_last_access = trim($last_access);

        return $this->request_xml_string_method(
            'GetLastAvailChangedProperties',
            $settings,
            [
                'LastAccess' => $normalized_last_access,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return string|WP_Error
     */
    public function fetch_last_avail_changed_properties_test_string(array $settings, string $last_access): string|WP_Error
    {
        $normalized_last_access = trim($last_access);

        return $this->request_xml_string_method(
            'GetLastAvailChangedPropertiesTest',
            $settings,
            [
                'LastAccess' => $normalized_last_access,
            ]
        );
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return array<string, string>|WP_Error
     */
    public function fetch_amenity_labels(array $settings): array|WP_Error
    {
        $definitions = $this->fetch_amenity_definitions($settings);
        if (is_wp_error($definitions)) {
            return $definitions;
        }

        return isset($definitions['labels']) && is_array($definitions['labels'])
            ? $definitions['labels']
            : [];
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return array{labels: array<string, string>, types: array<string, string>}|WP_Error
     */
    public function fetch_amenity_definitions(array $settings): array|WP_Error
    {
        $xml = $this->request_xml_string_method(
            'GetPropertyAmmenityNameXML',
            $settings,
            [
                'num' => '',
            ]
        );

        if (is_wp_error($xml)) {
            return $xml;
        }

        return $this->parse_amenity_definitions($xml);
    }

    private function build_method_url(string $method): string
    {
        $base_url = (string) apply_filters('barefoot_engine_api_base_url', self::DEFAULT_BASE_URL);

        return rtrim($base_url, '/') . '/' . ltrim($method, '/');
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @param array<string, mixed> $extra_params
     * @return string|WP_Error
     */
    private function request_xml_string_method(string $method, array $settings, array $extra_params = []): string|WP_Error
    {
        $response = $this->request_method($method, $settings, $extra_params);
        if (is_wp_error($response)) {
            return $response;
        }

        $body = isset($response['body']) && is_string($response['body']) ? trim($response['body']) : '';
        if ($body === '') {
            return new WP_Error(
                'barefoot_engine_api_empty_response',
                __('Barefoot returned an empty response.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $raw = $this->extract_xml_string($body);
        if ($raw === '') {
            return new WP_Error(
                'barefoot_engine_api_invalid_response',
                __('Barefoot returned an invalid XML payload.', 'barefoot-engine'),
                [
                    'status' => 502,
                    'details' => $this->summarize_remote_error($body),
                ]
            );
        }

        return $raw;
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @param array<string, mixed> $extra_params
     * @return string|WP_Error
     */
    private function request_xml_document_method(string $method, array $settings, array $extra_params = []): string|WP_Error
    {
        $response = $this->request_method($method, $settings, $extra_params);
        if (is_wp_error($response)) {
            return $response;
        }

        $body = isset($response['body']) && is_string($response['body']) ? trim($response['body']) : '';
        if ($body === '') {
            return new WP_Error(
                'barefoot_engine_api_empty_response',
                __('Barefoot returned an empty response.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $raw = $this->extract_xml_string($body);
        $document = $this->load_document($raw);
        if ($document === null || !$document->documentElement instanceof \DOMElement) {
            return new WP_Error(
                'barefoot_engine_api_invalid_response',
                __('Barefoot returned an invalid XML payload.', 'barefoot-engine'),
                [
                    'status' => 502,
                    'details' => $this->summarize_remote_error($body),
                ]
            );
        }

        return $raw;
    }

    /**
     * @param array<string, mixed> $extra_params
     * @return string|WP_Error
     */
    private function request_scalar_method(string $method, array $settings, array $extra_params = []): string|WP_Error
    {
        $response = $this->request_method($method, $settings, $extra_params);
        if (is_wp_error($response)) {
            return $response;
        }

        $body = isset($response['body']) && is_string($response['body']) ? trim($response['body']) : '';
        if ($body === '') {
            return new WP_Error(
                'barefoot_engine_api_empty_response',
                __('Barefoot returned an empty response.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $value = $this->extract_scalar_text($body);
        if ($value === '') {
            return new WP_Error(
                'barefoot_engine_api_invalid_response',
                __('Barefoot returned an invalid scalar payload.', 'barefoot-engine'),
                [
                    'status' => 502,
                    'details' => $this->summarize_remote_error($body),
                ]
            );
        }

        return $value;
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @param array<string, mixed> $extra_params
     * @return array<string, mixed>|WP_Error
     */
    private function request_method(string $method, array $settings, array $extra_params = []): array|WP_Error
    {
        $credentials = $this->build_credentials($settings);
        $response = wp_remote_post(
            $this->build_method_url($method),
            [
                'timeout' => 20,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8',
                ],
                'body' => $this->build_request_body(array_merge($credentials, $extra_params)),
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'barefoot_engine_api_request_failed',
                __('Unable to reach the Barefoot API endpoint.', 'barefoot-engine'),
                [
                    'status' => 502,
                    'details' => $response->get_error_message(),
                ]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $response_message = (string) wp_remote_retrieve_response_message($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($status_code < 200 || $status_code >= 300) {
            return $this->build_remote_error($status_code, $response_message, $body);
        }

        return [
            'status_code' => $status_code,
            'body' => $body,
        ];
    }

    /**
     * @param array<string, mixed> $params
     */
    private function build_request_body(array $params): string
    {
        $segments = [];

        foreach ($params as $key => $value) {
            $normalized_key = (string) $key;
            if ($normalized_key === '') {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if (!is_scalar($item) && $item !== null) {
                        continue;
                    }

                    $segments[] = rawurlencode($normalized_key) . '=' . rawurlencode($this->normalize_request_value($item));
                }

                continue;
            }

            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $segments[] = rawurlencode($normalized_key) . '=' . rawurlencode($this->normalize_request_value($value));
        }

        return implode('&', $segments);
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @return array<string, string>
     */
    private function build_credentials(array $settings): array
    {
        $api = isset($settings['api']) && is_array($settings['api']) ? $settings['api'] : [];

        return [
            'username' => isset($api['username']) && is_string($api['username']) ? $api['username'] : '',
            'password' => isset($api['password']) && is_string($api['password']) ? $api['password'] : '',
            'barefootAccount' => isset($api['company_id']) && is_string($api['company_id']) ? $api['company_id'] : '',
        ];
    }

    private function extract_xml_string(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $document = $this->load_document($trimmed);
        if ($document === null || !$document->documentElement instanceof \DOMElement) {
            return $trimmed;
        }

        $root = $document->documentElement;
        if (strtolower($root->localName) === 'string') {
            $decoded = html_entity_decode($root->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8');

            return trim($decoded);
        }

        return $trimmed;
    }

    private function extract_scalar_text(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $document = $this->load_document($trimmed);
        if ($document === null || !$document->documentElement instanceof \DOMElement) {
            return trim(html_entity_decode(wp_strip_all_tags($trimmed), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }

        return trim(html_entity_decode($document->documentElement->textContent, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    private function parse_boolean_response(string $body): ?bool
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return null;
        }

        $document = $this->load_document($trimmed);
        if ($document === null || !$document->documentElement instanceof \DOMElement) {
            $normalized = strtolower($trimmed);
            if (str_contains($normalized, 'true')) {
                return true;
            }

            if (str_contains($normalized, 'false')) {
                return false;
            }

            return null;
        }

        $value = strtolower(trim($document->documentElement->textContent));
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        return null;
    }

    /**
     * @return array{labels: array<string, string>, types: array<string, string>}|WP_Error
     */
    private function parse_amenity_definitions(string $xml): array|WP_Error
    {
        $document = $this->load_document($xml, true);
        if ($document === null) {
            return new WP_Error(
                'barefoot_engine_api_invalid_amenities_xml',
                __('Barefoot returned invalid amenity label XML.', 'barefoot-engine'),
                ['status' => 502]
            );
        }

        $xpath = new \DOMXPath($document);
        $nodes = $xpath->query('//*[local-name()="PropertyAmmenity"]');
        if ($nodes === false) {
            return [];
        }

        $labels = [];
        $types = [];

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $key = '';
            $label = '';
            $type = '';

            foreach ($node->childNodes as $child) {
                if (!$child instanceof \DOMElement) {
                    continue;
                }

                $local_name = $child->localName;
                if ($local_name === 'a_name') {
                    $key = trim($child->textContent);
                } elseif ($local_name === 'a_value') {
                    $label = trim($child->textContent);
                } elseif ($local_name === 'a_type') {
                    $type = trim($child->textContent);
                }
            }

            if ($key === '' || $label === '') {
                continue;
            }

            $labels[$key] = $label;

            if ($type !== '') {
                $types[$key] = $type;
            }
        }

        return [
            'labels' => $labels,
            'types' => $types,
        ];
    }

    private function load_document(string $xml, bool $wrap_if_needed = false): ?\DOMDocument
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);

        if (!$loaded && $wrap_if_needed) {
            libxml_clear_errors();
            $loaded = $document->loadXML('<root>' . $xml . '</root>', LIBXML_NONET | LIBXML_NOCDATA);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        return $document;
    }

    /**
     * @return WP_Error
     */
    private function build_remote_error(int $status_code, string $response_message, string $body)
    {
        if ($this->is_auth_failure($body)) {
            return new WP_Error(
                'barefoot_engine_api_auth_failed',
                __('Barefoot rejected the credentials. Verify the username, password, and Barefoot account / portal ID.', 'barefoot-engine'),
                [
                    'status' => 401,
                    'remote_status' => $status_code,
                    'details' => $this->summarize_remote_error($body),
                ]
            );
        }

        $message = $response_message !== ''
            ? sprintf(
                /* translators: 1: HTTP status code, 2: HTTP status message */
                __('Barefoot API request failed (%1$d %2$s).', 'barefoot-engine'),
                $status_code,
                $response_message
            )
            : sprintf(
                /* translators: %d: HTTP status code */
                __('Barefoot API request failed (%d).', 'barefoot-engine'),
                $status_code
            );

        return new WP_Error(
            'barefoot_engine_api_http_error',
            $message,
            [
                'status' => 502,
                'remote_status' => $status_code,
                'details' => $this->summarize_remote_error($body),
            ]
        );
    }

    private function is_auth_failure(string $body): bool
    {
        $normalized = strtolower($body);

        return str_contains($normalized, 'access denied')
            || str_contains($normalized, 'verifycredentials')
            || str_contains($normalized, 'authenticate(');
    }

    private function summarize_remote_error(string $body): string
    {
        $normalized = preg_replace('/\s+/', ' ', wp_strip_all_tags($body));
        if (!is_string($normalized)) {
            return '';
        }

        return trim(wp_html_excerpt($normalized, 220, '...'));
    }

    /**
     * @param scalar|null $value
     */
    private function normalize_request_value($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
