<?php

namespace BarefootEngine\Properties;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Listings_Provider
{
    private const ACTIVE_IMPORT_STATUS = 'active';

    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $cached_listings = null;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_active_listings(): array
    {
        if ($this->cached_listings !== null) {
            return $this->cached_listings;
        }

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

            $listing = $this->build_listing($post);
            if ($listing !== null) {
                $listings[] = $listing;
            }
        }

        $this->cached_listings = $listings;

        return $this->cached_listings;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build_listing(\WP_Post $post): ?array
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

        $guest_count = $this->resolve_guest_count($fields);
        $bedrooms = $this->resolve_bedrooms($fields);
        $property_type = $this->clean_string($fields['a259'] ?? '');
        $coordinates = $this->resolve_coordinates($fields);

        if ($guest_count === null && $property_type === '' && $coordinates === null) {
            return null;
        }

        $listing = [
            'id' => $this->resolve_listing_id($post, $property_id, $keyboard_id),
            'title' => $title,
            'images' => $this->resolve_images($post, $fields),
            'searchData' => $this->build_search_data($post, $fields, $title, $guest_count, $bedrooms, $property_type),
            'permalink' => get_permalink($post),
        ];

        $subtitle = $this->build_subtitle($fields);
        if ($subtitle !== '') {
            $listing['subtitle'] = $subtitle;
        }

        $details = $this->build_details($guest_count, $bedrooms);
        if ($details !== '') {
            $listing['details'] = $details;
        }

        $price = $this->resolve_price($fields);
        if ($price !== null) {
            $listing['price'] = $price;
        }

        if ($property_type !== '') {
            $listing['tag'] = $property_type;
        }

        if ($coordinates !== null && $price !== null) {
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
        $title_candidates = [
            $post->post_title,
            $fields['name'] ?? '',
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
        $parts = [
            $this->clean_string($fields['city'] ?? ''),
            $this->clean_string($fields['state'] ?? ''),
            $this->clean_string($fields['country'] ?? ''),
        ];

        return implode(', ', array_values(array_filter(array_unique($parts))));
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function build_details(?int $guest_count, ?int $bedrooms): string
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
     * @return int|string|null
     */
    private function resolve_price(array $fields)
    {
        foreach (['minprice', 'maxprice'] as $price_key) {
            $price = $this->normalize_price($fields[$price_key] ?? null);
            if ($price !== null) {
                return $price;
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @return int|string|null
     */
    private function normalize_price($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 0) {
                return (int) round($numeric);
            }
        }

        $normalized = preg_replace('/[^\d.]/', '', (string) $value);
        if ($normalized === null || $normalized === '') {
            return null;
        }

        if (is_numeric($normalized)) {
            $numeric = (float) $normalized;
            if ($numeric > 0) {
                return (int) round($numeric);
            }
        }

        return null;
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

        return array_values(array_filter(array_unique($images)));
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
    private function resolve_guest_count(array $fields): ?int
    {
        foreach (['a53', 'occupancy'] as $key) {
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
    private function resolve_bedrooms(array $fields): ?int
    {
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
    private function clean_string($value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim(sanitize_text_field((string) $value));
    }
}
