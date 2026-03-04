<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Property_Sync_Service;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Listings_Provider
{
    private const ACTIVE_IMPORT_STATUS = 'active';
    private const WEEKDAY_MAP = [
        'sun' => 0,
        'mon' => 1,
        'tue' => 2,
        'wed' => 3,
        'thu' => 4,
        'fri' => 5,
        'sat' => 6,
    ];

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $cached_listings = null;

    private ?string $cached_target_date = null;
    private ?bool $cached_has_selected_check_in = null;
    private Property_Availability_Service $availability_service;

    public function __construct(?Property_Availability_Service $availability_service = null)
    {
        $this->availability_service = $availability_service ?? new Property_Availability_Service();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_active_listings(): array
    {
        if ($this->cached_listings !== null) {
            return $this->cached_listings;
        }

        $target_date = $this->resolve_target_date();
        $has_selected_check_in = $this->has_selected_check_in();
        $check_in = $this->resolve_check_in();
        $check_out = $this->resolve_check_out();

        $posts = get_posts(
            [
                'post_type' => Property_Post_Type::POST_TYPE,
                'post_status' => 'publish',
                'meta_key' => '_be_property_import_status',
                'meta_value' => self::ACTIVE_IMPORT_STATUS,
                'numberposts' => -1,
                'orderby' => 'title',
                'order' => 'ASC',
            ]
        );

        $listings = [];

        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post) {
                continue;
            }

            $listing = $this->build_listing($post, $target_date, $has_selected_check_in);
            if ($listing !== null) {
                $listings[] = $listing;
            }
        }

        if ($this->availability_service->has_valid_date_range($check_in, $check_out)) {
            $cached_available_property_ids = $this->availability_service->get_cached_available_property_ids($check_in, $check_out);

            if (is_array($cached_available_property_ids)) {
                $available_lookup = array_fill_keys($cached_available_property_ids, true);
                $listings = array_values(
                    array_filter(
                        $listings,
                        static function (array $listing) use ($available_lookup): bool {
                            $property_id = isset($listing['propertyId']) && is_scalar($listing['propertyId'])
                                ? trim((string) $listing['propertyId'])
                                : '';

                            return $property_id !== '' && isset($available_lookup[$property_id]);
                        }
                    )
                );
            }
        }

        $this->cached_listings = $listings;

        return $this->cached_listings;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build_listing(\WP_Post $post, string $target_date, bool $has_selected_check_in): ?array
    {
        $fields = get_post_meta($post->ID, '_be_property_fields', true);
        if (!is_array($fields)) {
            $fields = [];
        }

        $property_id = $this->clean_string(get_post_meta($post->ID, '_be_property_id', true));
        $keyboard_id = $this->clean_string(get_post_meta($post->ID, '_be_property_keyboardid', true));
        $title = $this->resolve_title($post, $fields, $keyboard_id, $property_id);
        if ($title === '') {
            return null;
        }

        $guest_count = $this->resolve_guest_count($post, $fields);
        $bedrooms = $this->resolve_bedrooms($post, $fields);
        $bathrooms = $this->resolve_bathrooms($post, $fields);
        $property_type = $this->clean_string($fields['a259'] ?? '');
        $coordinates = $this->resolve_coordinates($fields);
        $pricing_data = $this->resolve_pricing_data($post, $target_date, $has_selected_check_in);

        if ($guest_count === null && $property_type === '' && $coordinates === null) {
            return null;
        }

        $listing = [
            'id' => $this->resolve_listing_id($post, $property_id, $keyboard_id),
            'propertyId' => $property_id,
            'title' => $title,
            'images' => $this->resolve_images($post, $fields),
            'searchData' => $this->build_search_data($post, $fields, $title, $guest_count, $bedrooms, $property_type),
            'permalink' => get_permalink($post),
        ];

        $subtitle = $this->build_subtitle($fields);
        if ($subtitle !== '') {
            $listing['subtitle'] = $subtitle;
        }

        $details = $this->build_details($guest_count, $bedrooms, $bathrooms);
        if ($details !== '') {
            $listing['details'] = $details;
        }

        $badge = $this->resolve_badge($fields);
        if ($badge !== '') {
            $listing['badge'] = $badge;
        }

        if ($pricing_data !== null) {
            $listing['pricingData'] = $pricing_data;

            if (isset($pricing_data['price']) && is_numeric($pricing_data['price'])) {
                $listing['price'] = $pricing_data['price'];
            }

            if (isset($pricing_data['pricePeriod']) && is_string($pricing_data['pricePeriod']) && $pricing_data['pricePeriod'] !== '') {
                $listing['pricePeriod'] = $pricing_data['pricePeriod'];
            }
        }

        if ($coordinates !== null && isset($listing['price'])) {
            $listing['lat'] = $coordinates['lat'];
            $listing['lng'] = $coordinates['lng'];
        }

        return $listing;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolve_title(\WP_Post $post, array $fields, string $keyboard_id, string $property_id): string
    {
        $name = $this->clean_string($fields['name'] ?? '');
        $unit_type = $this->clean_string($fields['a259'] ?? '');

        if ($name !== '' && $unit_type !== '') {
            return 'Unit ' . $name . ' · ' . $unit_type;
        }

        $title_candidates = [
            $name,
            $unit_type,
            $post->post_title,
            $keyboard_id,
            $property_id,
        ];

        foreach ($title_candidates as $candidate) {
            $title = $this->clean_string($candidate);
            if ($title !== '') {
                return $title;
            }
        }

        return '';
    }

    private function resolve_listing_id(\WP_Post $post, string $property_id, string $keyboard_id): string
    {
        foreach ([$property_id, $keyboard_id, (string) $post->ID] as $candidate) {
            $value = $this->clean_string($candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return (string) $post->ID;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function build_subtitle(array $fields): string
    {
        return $this->clean_string($fields['PropertyTitle'] ?? '');
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function build_details(?int $guest_count, ?int $bedrooms, ?string $bathrooms): string
    {
        $parts = [];

        if ($bedrooms !== null) {
            $parts[] = $bedrooms === 0
                ? __('Studio', 'barefoot-engine')
                : sprintf(
                    _n('%d bedroom', '%d bedrooms', $bedrooms, 'barefoot-engine'),
                    $bedrooms
                );
        }

        if ($bathrooms !== null && $bathrooms !== '') {
            $numeric_bathrooms = is_numeric($bathrooms) ? (float) $bathrooms : null;
            if ($numeric_bathrooms !== null && abs($numeric_bathrooms - floor($numeric_bathrooms)) < 0.00001) {
                $parts[] = sprintf(
                    _n('%d bathroom', '%d bathrooms', (int) $numeric_bathrooms, 'barefoot-engine'),
                    (int) $numeric_bathrooms
                );
            } else {
                $parts[] = sprintf(
                    /* translators: %s is the bathroom count for a property. */
                    __('%s bathrooms', 'barefoot-engine'),
                    $bathrooms
                );
            }
        }

        if ($guest_count !== null) {
            $parts[] = sprintf(
                /* translators: %d is the guest capacity for a property. */
                __('Sleeps %d', 'barefoot-engine'),
                $guest_count
            );
        }

        return implode(' · ', $parts);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function build_search_data(
        \WP_Post $post,
        array $fields,
        string $title,
        ?int $guest_count,
        ?int $bedrooms,
        string $property_type
    ): array
    {
        $location_parts = array_values(
            array_filter(
                array_unique(
                    [
                        $title,
                        $this->clean_string($fields['PropertyTitle'] ?? ''),
                        $this->clean_string($fields['propAddress'] ?? ''),
                        $this->clean_string($fields['propAddressNew'] ?? ''),
                        $this->clean_string($fields['city'] ?? ''),
                        $this->clean_string($fields['state'] ?? ''),
                        $this->clean_string($fields['country'] ?? ''),
                    ]
                )
            )
        );

        $field_values = [];
        $filter_values = [];

        if ($guest_count !== null) {
            $field_values['guests'] = (string) $guest_count;
        }

        if ($bedrooms !== null) {
            $filter_values['bedrooms'] = $bedrooms;
        }

        if ($property_type !== '') {
            $filter_values['property_type'] = $property_type;
        }

        $view = $this->clean_string($fields['a261'] ?? '');
        if ($view !== '') {
            $filter_values['view'] = $view;
        }

        return [
            'location' => $location_parts,
            'fields' => $field_values,
            'filters' => $filter_values,
            'availability' => [],
            'postId' => $post->ID,
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<int, string>
     */
    private function resolve_images(\WP_Post $post, array $fields): array
    {
        $images = [];

        $stored_images = get_post_meta($post->ID, '_be_property_images', true);
        if (is_array($stored_images)) {
            foreach ($stored_images as $image) {
                if (!is_string($image) || trim($image) === '') {
                    continue;
                }

                $images[] = esc_url_raw($image);
            }
        }

        $thumbnail_url = get_the_post_thumbnail_url($post, 'large');
        if (is_string($thumbnail_url) && $thumbnail_url !== '') {
            $images[] = $thumbnail_url;
        }

        $image_sources = [
            $fields['imagepath'] ?? '',
            $fields['image_path'] ?? '',
        ];

        foreach ($image_sources as $source) {
            if (!is_string($source) || trim($source) === '') {
                continue;
            }

            preg_match_all('~https?://[^\\s,"\']+~i', $source, $matches);
            if (!empty($matches[0])) {
                foreach ($matches[0] as $url) {
                    $images[] = esc_url_raw($url);
                }
            }
        }

        return array_slice(array_values(array_filter(array_unique($images))), 0, 8);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolve_badge(array $fields): string
    {
        $unit_grade = $this->clean_string($fields['a267'] ?? '');
        if ($unit_grade === '') {
            return '';
        }

        return sprintf(
            /* translators: %s is the property unit grade, such as A+ */
            __('Rating: %s', 'barefoot-engine'),
            $unit_grade
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolve_pricing_data(\WP_Post $post, string $target_date, bool $has_selected_check_in): ?array
    {
        $rates = get_post_meta($post->ID, '_be_property_rates', true);
        if (!is_array($rates) || $rates === []) {
            return null;
        }

        $pricing_data = [
            'targetDate' => $target_date,
            'selectedType' => '',
            'rates' => $rates,
            'isStartingPrice' => !$has_selected_check_in,
        ];

        $selected_rate = $has_selected_check_in
            ? $this->find_matching_rate($rates, $target_date)
            : $this->find_starting_rate($rates);
        if ($selected_rate === null) {
            return $pricing_data;
        }

        $amount = isset($selected_rate['amount']) && is_numeric($selected_rate['amount'])
            ? (float) $selected_rate['amount']
            : null;

        if ($amount === null || $amount <= 0) {
            return $pricing_data;
        }

        $selected_type = isset($selected_rate['pricetype']) && is_string($selected_rate['pricetype'])
            ? strtolower(trim($selected_rate['pricetype']))
            : '';

        $pricing_data['selectedType'] = $has_selected_check_in ? $selected_type : 'starting';
        $pricing_data['price'] = $amount;
        $pricing_data['pricePeriod'] = !$has_selected_check_in
            ? __('per night', 'barefoot-engine')
            : (
                $selected_type === 'weekendany'
                    ? __('weekend night', 'barefoot-engine')
                    : __('per night', 'barefoot-engine')
            );

        return $pricing_data;
    }

    /**
     * @param array<string, mixed> $rates
     * @return array<string, mixed>|null
     */
    private function find_matching_rate(array $rates, string $target_date): ?array
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
    private function find_starting_rate(array $rates): ?array
    {
        $candidates = [];

        foreach (['daily', 'weekendany'] as $rate_type) {
            $typed_rates = isset($rates['by_type'][$rate_type]) && is_array($rates['by_type'][$rate_type])
                ? $rates['by_type'][$rate_type]
                : [];

            foreach ($typed_rates as $rate) {
                if (!is_array($rate)) {
                    continue;
                }

                $amount = isset($rate['amount']) && is_numeric($rate['amount']) ? (float) $rate['amount'] : null;
                if ($amount === null || $amount <= 0) {
                    continue;
                }

                $candidates[] = $rate;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static function (array $left, array $right): int {
                return ((float) ($left['amount'] ?? 0)) <=> ((float) ($right['amount'] ?? 0));
            }
        );

        return $candidates[0] ?? null;
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
        $start = isset($rate['date_start']) && is_string($rate['date_start']) ? trim($rate['date_start']) : '';
        $end = isset($rate['date_end']) && is_string($rate['date_end']) ? trim($rate['date_end']) : '';

        if (!$this->is_valid_ymd_date($start) || !$this->is_valid_ymd_date($end)) {
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

        $target_weekday = (int) wp_date('w', strtotime($target_date . ' 00:00:00'));

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

    private function resolve_target_date(): string
    {
        if ($this->cached_target_date !== null) {
            return $this->cached_target_date;
        }

        $candidate = isset($_GET['check_in']) && is_scalar($_GET['check_in'])
            ? trim(wp_unslash((string) $_GET['check_in']))
            : '';

        if ($this->is_valid_ymd_date($candidate)) {
            $this->cached_target_date = $candidate;

            return $this->cached_target_date;
        }

        $this->cached_target_date = current_datetime()->format('Y-m-d');

        return $this->cached_target_date;
    }

    private function has_selected_check_in(): bool
    {
        if ($this->cached_has_selected_check_in !== null) {
            return $this->cached_has_selected_check_in;
        }

        $candidate = isset($_GET['check_in']) && is_scalar($_GET['check_in'])
            ? trim(wp_unslash((string) $_GET['check_in']))
            : '';

        $this->cached_has_selected_check_in = $this->is_valid_ymd_date($candidate);

        return $this->cached_has_selected_check_in;
    }

    private function resolve_check_in(): string
    {
        $candidate = isset($_GET['check_in']) && is_scalar($_GET['check_in'])
            ? trim(wp_unslash((string) $_GET['check_in']))
            : '';

        return $this->is_valid_ymd_date($candidate) ? $candidate : '';
    }

    private function resolve_check_out(): string
    {
        $candidate = isset($_GET['check_out']) && is_scalar($_GET['check_out'])
            ? trim(wp_unslash((string) $_GET['check_out']))
            : '';

        return $this->is_valid_ymd_date($candidate) ? $candidate : '';
    }

    private function is_valid_ymd_date(string $value): bool
    {
        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        if (!$date instanceof \DateTimeImmutable) {
            return false;
        }

        return $date->format('Y-m-d') === $value;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{lat: float, lng: float}|null
     */
    private function resolve_coordinates(array $fields): ?array
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

    /**
     * @param array<string, mixed> $fields
     */
    private function resolve_guest_count(\WP_Post $post, array $fields): ?int
    {
        $stored_guest_count = $this->normalize_positive_integer(
            get_post_meta($post->ID, Property_Sync_Service::GUEST_COUNT_META_KEY, true)
        );
        if ($stored_guest_count !== null) {
            return $stored_guest_count;
        }

        foreach (['a53', 'SleepsBeds', 'occupancy'] as $key) {
            $guest_count = $this->normalize_positive_integer($fields[$key] ?? null);
            if ($guest_count === null) {
                continue;
            }

            if ($key === 'occupancy' && $guest_count > 40) {
                continue;
            }

            return $guest_count;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolve_bedrooms(\WP_Post $post, array $fields): ?int
    {
        $stored_bedrooms = $this->normalize_non_negative_integer(
            get_post_meta($post->ID, Property_Sync_Service::BEDROOM_COUNT_META_KEY, true)
        );
        if ($stored_bedrooms !== null) {
            return $stored_bedrooms;
        }

        $bedrooms = $this->normalize_non_negative_integer($fields['a56'] ?? null);
        if ($bedrooms !== null) {
            return $bedrooms;
        }

        $property_type = $this->clean_string($fields['a259'] ?? '');
        if ($property_type === '') {
            return null;
        }

        if (preg_match('/studio/i', $property_type) === 1) {
            return 0;
        }

        if (preg_match('/(\d+)\s*bedroom/i', $property_type, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function resolve_bathrooms(\WP_Post $post, array $fields): ?string
    {
        $stored_bathrooms = $this->normalize_non_negative_number(
            get_post_meta($post->ID, Property_Sync_Service::BATHROOM_COUNT_META_KEY, true)
        );
        if ($stored_bathrooms !== null) {
            return $stored_bathrooms;
        }

        $bathrooms = $this->normalize_non_negative_number($fields['a195'] ?? null);
        if ($bathrooms !== null) {
            return $bathrooms;
        }

        foreach (['a259', 'PropertyTitle'] as $key) {
            $parsed = $this->parse_count_from_text($fields[$key] ?? null, 'bath');
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function normalize_coordinate($value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;

        return max($min, min($max, $numeric));
    }

    /**
     * @param mixed $value
     */
    private function normalize_positive_integer($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (int) round((float) $value);

        return $numeric > 0 ? $numeric : null;
    }

    /**
     * @param mixed $value
     */
    private function normalize_non_negative_integer($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (int) round((float) $value);

        return $numeric >= 0 ? $numeric : null;
    }

    /**
     * @param mixed $value
     */
    private function normalize_non_negative_number($value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;
        if ($numeric < 0) {
            return null;
        }

        if (abs($numeric - floor($numeric)) < 0.00001) {
            return (string) (int) round($numeric);
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    /**
     * @param mixed $value
     */
    private function parse_count_from_text($value, string $kind): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $pattern = $kind === 'bath'
            ? '/(\d+(?:\.\d+)?)\s*(?:bathroom|bathrooms|bath|baths|ba)\b/i'
            : '/(\d+(?:\.\d+)?)\s*(?:bedroom|bedrooms|bed|beds)\b/i';

        if (preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        return $this->normalize_non_negative_number($matches[1] ?? null);
    }

    /**
     * @param mixed $value
     */
    private function clean_string($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(sanitize_text_field((string) $value));
    }
}
