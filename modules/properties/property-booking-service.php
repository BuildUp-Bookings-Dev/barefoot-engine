<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Api_Integration_Settings;
use BarefootEngine\Services\Barefoot_Api_Client;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Booking_Service
{
    private const DEFAULT_REZTYPE_ID = 26;
    private const CALENDAR_TRANSIENT_PREFIX = 'barefoot_engine_booking_calendar_';
    private const QUOTE_TRANSIENT_PREFIX = 'barefoot_engine_booking_quote_';
    private const DEFAULT_CALENDAR_CACHE_TTL = 900;
    private const DEFAULT_QUOTE_CACHE_TTL = 900;
    private const MAX_CALENDAR_RANGE_DAYS = 120;
    /**
     * @var array<string, int>
     */
    private const WEEKDAY_MAP = [
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];

    private Barefoot_Api_Client $api_client;
    private Api_Integration_Settings $api_settings;
    private Property_Parser $parser;

    public function __construct(
        ?Barefoot_Api_Client $api_client = null,
        ?Api_Integration_Settings $api_settings = null,
        ?Property_Parser $parser = null
    ) {
        $this->api_client = $api_client ?? new Barefoot_Api_Client();
        $this->api_settings = $api_settings ?? new Api_Integration_Settings();
        $this->parser = $parser ?? new Property_Parser();
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_calendar_data(string $property_id, string $month_start, string $month_end): array|WP_Error
    {
        $normalized_property_id = $this->normalize_property_id($property_id);
        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_booking_missing_property_id',
                __('A valid Barefoot Property ID is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $normalized_month_start = $this->normalize_ymd_date($month_start);
        $normalized_month_end = $this->normalize_ymd_date($month_end);
        if ($normalized_month_start === '' || $normalized_month_end === '') {
            return new WP_Error(
                'barefoot_engine_booking_invalid_calendar_range',
                __('A valid month date range is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        if ($normalized_month_end < $normalized_month_start) {
            return new WP_Error(
                'barefoot_engine_booking_invalid_calendar_range',
                __('The month end date must be after the month start date.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $range_days = $this->calculate_date_diff_days($normalized_month_start, $normalized_month_end);
        if ($range_days < 0 || $range_days > self::MAX_CALENDAR_RANGE_DAYS) {
            return new WP_Error(
                'barefoot_engine_booking_invalid_calendar_range',
                __('The requested calendar range is too large.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_booking_missing_credentials',
                __('Please save your Barefoot API credentials first.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $cache_key = $this->build_cache_key(
            self::CALENDAR_TRANSIENT_PREFIX,
            $settings,
            [
                'property_id' => $normalized_property_id,
                'month_start' => $normalized_month_start,
                'month_end' => $normalized_month_end,
            ]
        );
        $cached_payload = get_transient($cache_key);
        if (is_array($cached_payload)) {
            $cached_payload['cache'] = [
                'hit' => true,
                'fetched_at' => isset($cached_payload['cache']['fetched_at']) ? (int) $cached_payload['cache']['fetched_at'] : 0,
            ];

            return $cached_payload;
        }

        $response = $this->api_client->fetch_property_booking_date_xml(
            $settings,
            $normalized_property_id,
            $this->format_mdy_date($normalized_month_start),
            $this->format_mdy_date($normalized_month_end)
        );
        if (is_wp_error($response)) {
            return $response;
        }

        $parsed = $this->parser->parse_property_booking_dates($response);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        $blocked_ranges = isset($parsed['blocked_ranges']) && is_array($parsed['blocked_ranges'])
            ? array_values($parsed['blocked_ranges'])
            : [];
        $disabled_dates = $this->build_disabled_dates(
            $blocked_ranges,
            $normalized_month_start,
            $normalized_month_end
        );
        $daily_prices = $this->build_daily_prices(
            $normalized_property_id,
            $normalized_month_start,
            $normalized_month_end
        );

        $payload = [
            'property_id' => $normalized_property_id,
            'month_start' => $normalized_month_start,
            'month_end' => $normalized_month_end,
            'blocked_ranges' => $blocked_ranges,
            'disabled_dates' => $disabled_dates,
            'daily_prices' => $daily_prices,
            'cache' => [
                'hit' => false,
                'fetched_at' => time(),
            ],
        ];

        set_transient($cache_key, $payload, $this->get_calendar_cache_ttl());

        return $payload;
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_quote_data(
        string $property_id,
        string $check_in,
        string $check_out,
        int $guests,
        ?int $reztypeid = null
    ): array|WP_Error {
        $normalized_property_id = $this->normalize_property_id($property_id);
        if ($normalized_property_id === '') {
            return new WP_Error(
                'barefoot_engine_booking_missing_property_id',
                __('A valid Barefoot Property ID is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $normalized_check_in = $this->normalize_ymd_date($check_in);
        $normalized_check_out = $this->normalize_ymd_date($check_out);
        if ($normalized_check_in === '' || $normalized_check_out === '' || $normalized_check_out <= $normalized_check_in) {
            return new WP_Error(
                'barefoot_engine_booking_invalid_quote_range',
                __('A valid check-in and check-out range is required.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $normalized_guests = max(1, min(99, (int) $guests));
        $resolved_reztypeid = $reztypeid !== null && $reztypeid > 0 ? (int) $reztypeid : self::DEFAULT_REZTYPE_ID;

        $settings = $this->api_settings->get_settings();
        if (!$this->api_settings->has_required_credentials($settings)) {
            return new WP_Error(
                'barefoot_engine_booking_missing_credentials',
                __('Please save your Barefoot API credentials first.', 'barefoot-engine'),
                ['status' => 400]
            );
        }

        $cache_key = $this->build_cache_key(
            self::QUOTE_TRANSIENT_PREFIX,
            $settings,
            [
                'property_id' => $normalized_property_id,
                'check_in' => $normalized_check_in,
                'check_out' => $normalized_check_out,
                'guests' => $normalized_guests,
                'reztypeid' => $resolved_reztypeid,
            ]
        );
        $cached_payload = get_transient($cache_key);
        if (is_array($cached_payload)) {
            $cached_payload['cache'] = [
                'hit' => true,
                'fetched_at' => isset($cached_payload['cache']['fetched_at']) ? (int) $cached_payload['cache']['fetched_at'] : 0,
            ];

            return $cached_payload;
        }

        $is_available = $this->api_client->is_property_availability(
            $settings,
            $normalized_property_id,
            $this->format_mdy_date($normalized_check_in),
            $this->format_mdy_date($normalized_check_out)
        );
        if (is_wp_error($is_available)) {
            return $is_available;
        }

        if ($is_available !== true) {
            $payload = [
                'property_id' => $normalized_property_id,
                'check_in' => $normalized_check_in,
                'check_out' => $normalized_check_out,
                'guests' => $normalized_guests,
                'reztypeid' => $resolved_reztypeid,
                'available' => false,
                'status' => 'unavailable',
                'line_items' => [],
                'totals' => null,
                'cache' => [
                    'hit' => false,
                    'fetched_at' => time(),
                ],
            ];

            set_transient($cache_key, $payload, $this->get_quote_cache_ttl());

            return $payload;
        }

        $quote_response = $this->api_client->fetch_quote_rates_detail_string(
            $settings,
            $normalized_property_id,
            $this->format_mdy_date($normalized_check_in),
            $this->format_mdy_date($normalized_check_out),
            $normalized_guests,
            0,
            0,
            0,
            $resolved_reztypeid
        );
        if (is_wp_error($quote_response)) {
            return $quote_response;
        }

        if (stripos($quote_response, 'Property check rule failed') !== false) {
            $payload = [
                'property_id' => $normalized_property_id,
                'check_in' => $normalized_check_in,
                'check_out' => $normalized_check_out,
                'guests' => $normalized_guests,
                'reztypeid' => $resolved_reztypeid,
                'available' => false,
                'status' => 'unavailable',
                'line_items' => [],
                'totals' => null,
                'cache' => [
                    'hit' => false,
                    'fetched_at' => time(),
                ],
            ];

            set_transient($cache_key, $payload, $this->get_quote_cache_ttl());

            return $payload;
        }

        $parsed_quote = $this->parser->parse_quote_rates_detail($quote_response);
        if (is_wp_error($parsed_quote)) {
            return $parsed_quote;
        }

        $line_items = isset($parsed_quote['items']) && is_array($parsed_quote['items'])
            ? array_values($parsed_quote['items'])
            : [];
        $nights = max(1, $this->calculate_date_diff_days($normalized_check_in, $normalized_check_out));
        $totals = $this->calculate_quote_totals($line_items, $nights);

        $payload = [
            'property_id' => $normalized_property_id,
            'check_in' => $normalized_check_in,
            'check_out' => $normalized_check_out,
            'guests' => $normalized_guests,
            'reztypeid' => $resolved_reztypeid,
            'available' => true,
            'status' => 'available',
            'line_items' => $line_items,
            'totals' => $totals,
            'cache' => [
                'hit' => false,
                'fetched_at' => time(),
            ],
        ];

        set_transient($cache_key, $payload, $this->get_quote_cache_ttl());

        return $payload;
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
     * @param array<int, array<string, mixed>> $blocked_ranges
     * @return array<int, string>
     */
    private function build_disabled_dates(array $blocked_ranges, string $month_start, string $month_end): array
    {
        $disabled_dates = [];
        $window_start = \DateTimeImmutable::createFromFormat('!Y-m-d', $month_start, wp_timezone());
        $window_end = \DateTimeImmutable::createFromFormat('!Y-m-d', $month_end, wp_timezone());

        if (!$window_start instanceof \DateTimeImmutable || !$window_end instanceof \DateTimeImmutable) {
            return [];
        }

        foreach ($blocked_ranges as $blocked_range) {
            $arrival = isset($blocked_range['arrival_date']) ? $this->normalize_ymd_date((string) $blocked_range['arrival_date']) : '';
            $departure = isset($blocked_range['departure_date']) ? $this->normalize_ymd_date((string) $blocked_range['departure_date']) : '';

            if ($arrival === '' || $departure === '' || $departure <= $arrival) {
                continue;
            }

            $cursor = \DateTimeImmutable::createFromFormat('!Y-m-d', $arrival, wp_timezone());
            $departure_exclusive = \DateTimeImmutable::createFromFormat('!Y-m-d', $departure, wp_timezone());

            if (!$cursor instanceof \DateTimeImmutable || !$departure_exclusive instanceof \DateTimeImmutable) {
                continue;
            }

            while ($cursor < $departure_exclusive) {
                if ($cursor >= $window_start && $cursor <= $window_end) {
                    $disabled_dates[] = $cursor->format('Y-m-d');
                }

                $cursor = $cursor->modify('+1 day');
            }
        }

        $disabled_dates = array_values(array_unique($disabled_dates));
        sort($disabled_dates, SORT_STRING);

        return $disabled_dates;
    }

    /**
     * @return array<string, float>
     */
    private function build_daily_prices(string $property_id, string $month_start, string $month_end): array
    {
        $rates = $this->load_property_rates($property_id);
        if ($rates === []) {
            return [];
        }

        $window_start = \DateTimeImmutable::createFromFormat('!Y-m-d', $month_start, wp_timezone());
        $window_end = \DateTimeImmutable::createFromFormat('!Y-m-d', $month_end, wp_timezone());
        if (!$window_start instanceof \DateTimeImmutable || !$window_end instanceof \DateTimeImmutable) {
            return [];
        }

        $prices = [];
        $cursor = $window_start;

        while ($cursor <= $window_end) {
            $target_date = $cursor->format('Y-m-d');
            $rate = $this->find_matching_rate_for_date($rates, $target_date);
            if (is_array($rate)) {
                $amount = $this->normalize_rate_amount($rate['amount'] ?? $rate['rent'] ?? null);
                if ($amount !== null && $amount > 0) {
                    $prices[$target_date] = $this->normalize_money($amount);
                }
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $prices;
    }

    /**
     * @return array<string, mixed>
     */
    private function load_property_rates(string $property_id): array
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
            return [];
        }

        $post_id = (int) $query->posts[0];
        if ($post_id <= 0) {
            return [];
        }

        $rates = get_post_meta($post_id, '_be_property_rates', true);

        return is_array($rates) ? $rates : [];
    }

    /**
     * @param array<string, mixed> $rates
     * @return array<string, mixed>|null
     */
    private function find_matching_rate_for_date(array $rates, string $target_date): ?array
    {
        $weekend_rate = $this->find_matching_weekend_rate($rates, $target_date);
        if ($weekend_rate !== null) {
            return $weekend_rate;
        }

        return $this->find_matching_daily_rate($rates, $target_date);
    }

    /**
     * @param array<string, mixed> $rates
     * @return array<string, mixed>|null
     */
    private function find_matching_daily_rate(array $rates, string $target_date): ?array
    {
        $daily_rates = isset($rates['by_type']['daily']) && is_array($rates['by_type']['daily'])
            ? $rates['by_type']['daily']
            : [];

        foreach ($daily_rates as $rate) {
            if (!is_array($rate) || !$this->matches_rate_window($rate, $target_date)) {
                continue;
            }

            return $rate;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rates
     * @return array<string, mixed>|null
     */
    private function find_matching_weekend_rate(array $rates, string $target_date): ?array
    {
        $weekend_rates = isset($rates['by_type']['weekendany']) && is_array($rates['by_type']['weekendany'])
            ? $rates['by_type']['weekendany']
            : [];

        foreach ($weekend_rates as $rate) {
            if (!is_array($rate) || !$this->matches_rate_window($rate, $target_date)) {
                continue;
            }

            if ($this->matches_weekend_range($rate, $target_date)) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function matches_rate_window(array $rate, string $target_date): bool
    {
        $start = $this->normalize_rate_date($rate['date_start'] ?? $rate['date1'] ?? '');
        $end = $this->normalize_rate_date($rate['date_end'] ?? $rate['date2'] ?? '');

        if ($start === '' || $end === '' || $end < $start) {
            return false;
        }

        return $target_date >= $start && $target_date <= $end;
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function matches_weekend_range(array $rate, string $target_date): bool
    {
        $week_start = isset($rate['wk_b']) && is_scalar($rate['wk_b']) ? $this->normalize_weekday((string) $rate['wk_b']) : null;
        $week_end = isset($rate['wk_e']) && is_scalar($rate['wk_e']) ? $this->normalize_weekday((string) $rate['wk_e']) : null;

        if ($week_start === null || $week_end === null) {
            return false;
        }

        $target_timestamp = strtotime($target_date . ' 00:00:00');
        if ($target_timestamp === false) {
            return false;
        }

        $target_weekday = (int) wp_date('w', $target_timestamp);

        if ($week_start <= $week_end) {
            return $target_weekday >= $week_start && $target_weekday <= $week_end;
        }

        return $target_weekday >= $week_start || $target_weekday <= $week_end;
    }

    private function normalize_weekday(string $value): ?int
    {
        $normalized = strtolower(substr(trim($value), 0, 3));
        if ($normalized === '') {
            return null;
        }

        return self::WEEKDAY_MAP[$normalized] ?? null;
    }

    private function normalize_rate_date(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            $timestamp = strtotime($raw);
            if ($timestamp !== false) {
                return gmdate('Y-m-d', $timestamp);
            }
        }

        if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $raw) === 1) {
            $timestamp = strtotime($raw);
            if ($timestamp !== false) {
                return gmdate('Y-m-d', $timestamp);
            }
        }

        return '';
    }

    private function normalize_rate_amount(mixed $value): ?float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        if (!is_scalar($value)) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d\.\-]/', '', $raw);
        if (!is_string($normalized) || $normalized === '' || !is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
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

    private function normalize_money(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }

    /**
     * @param array<string, array<string, string>> $settings
     * @param array<string, mixed> $payload
     */
    private function build_cache_key(string $prefix, array $settings, array $payload): string
    {
        $api = isset($settings['api']) && is_array($settings['api']) ? $settings['api'] : [];
        $barefoot_account = isset($api['company_id']) && is_string($api['company_id']) ? trim($api['company_id']) : '';
        $hash_payload = array_merge(
            [
                'barefootAccount' => $barefoot_account,
            ],
            $payload
        );

        return $prefix . md5((string) wp_json_encode($hash_payload));
    }

    private function get_calendar_cache_ttl(): int
    {
        return max(
            60,
            (int) apply_filters('barefoot_engine_booking_calendar_cache_ttl', self::DEFAULT_CALENDAR_CACHE_TTL)
        );
    }

    private function get_quote_cache_ttl(): int
    {
        return max(
            60,
            (int) apply_filters('barefoot_engine_booking_quote_cache_ttl', self::DEFAULT_QUOTE_CACHE_TTL)
        );
    }
}
