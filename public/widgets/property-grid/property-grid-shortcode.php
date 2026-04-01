<?php

namespace BarefootEngine\Widgets\PropertyGrid;

use BarefootEngine\Properties\Property_Listings_Provider;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Grid_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_property_grid';

    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'columns_desktop' => '3',
        'columns_tablet' => '2',
        'columns_mobile' => '1',
        'limit' => '9',
        'paginated' => 'true',
        'show_filter' => 'true',
        'prefilter_type' => '',
        'prefilter_bedrooms' => '',
        'prefilter_bathrooms' => '',
        'prefilter_guests' => '',
        'prefilter_rating' => '',
        'empty_text' => '',
        'class' => '',
    ];

    private Property_Listings_Provider $property_listings_provider;

    public function __construct(?Property_Listings_Provider $property_listings_provider = null)
    {
        $this->property_listings_provider = $property_listings_provider ?? new Property_Listings_Provider();
    }

    public function register(): void
    {
        add_shortcode(self::SHORTCODE_TAG, [$this, 'render']);
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function render(array $atts = []): string
    {
        $attributes = shortcode_atts(self::DEFAULTS, is_array($atts) ? $atts : [], self::SHORTCODE_TAG);
        $instance_id = wp_unique_id('be-property-grid-');
        $config_id = $instance_id . '-config';
        $wrapper_classes = ['barefoot-engine-property-grid', 'barefoot-engine-public'];

        foreach ($this->sanitize_class_names((string) $attributes['class']) as $class_name) {
            $wrapper_classes[] = $class_name;
        }

        $config = [
            'columns' => [
                'desktop' => $this->normalize_positive_int($attributes['columns_desktop'] ?? '', 3, 1, 6),
                'tablet' => $this->normalize_positive_int($attributes['columns_tablet'] ?? '', 2, 1, 4),
                'mobile' => $this->normalize_positive_int($attributes['columns_mobile'] ?? '', 1, 1, 2),
            ],
            'limit' => $this->normalize_limit($attributes['limit'] ?? self::DEFAULTS['limit']),
            'paginated' => $this->normalize_boolean($attributes['paginated'] ?? '', true),
            'showFilter' => $this->normalize_boolean($attributes['show_filter'] ?? '', true),
            'initialFilters' => [
                'type' => $this->sanitize_text($attributes['prefilter_type'] ?? ''),
                'bedrooms' => $this->normalize_prefilter_bedrooms($attributes['prefilter_bedrooms'] ?? ''),
                'bathrooms' => $this->normalize_prefilter_bathrooms($attributes['prefilter_bathrooms'] ?? ''),
                'guests' => $this->normalize_prefilter_guests($attributes['prefilter_guests'] ?? ''),
                'rating' => $this->sanitize_text($attributes['prefilter_rating'] ?? ''),
            ],
            'emptyText' => $this->normalize_text(
                $attributes['empty_text'] ?? '',
                __('No properties matched your filters.', 'barefoot-engine')
            ),
            'labels' => [
                'type' => __('Type', 'barefoot-engine'),
                'bedrooms' => __('Bedrooms', 'barefoot-engine'),
                'bathrooms' => __('Bathrooms', 'barefoot-engine'),
                'guests' => __('Guests', 'barefoot-engine'),
                'rating' => __('Grade Letter', 'barefoot-engine'),
                'allTypes' => __('All types', 'barefoot-engine'),
                'allBedrooms' => __('All bedrooms', 'barefoot-engine'),
                'allBathrooms' => __('All bathrooms', 'barefoot-engine'),
                'allGuests' => __('All guests', 'barefoot-engine'),
                'allRatings' => __('All grades', 'barefoot-engine'),
                'reset' => __('Reset', 'barefoot-engine'),
                'page' => __('Page', 'barefoot-engine'),
                'previous' => __('Previous', 'barefoot-engine'),
                'next' => __('Next', 'barefoot-engine'),
                'propertySingular' => __('property found', 'barefoot-engine'),
                'propertyPlural' => __('properties found', 'barefoot-engine'),
                'propertyType' => __('Type', 'barefoot-engine'),
                'ratingMeta' => __('Grade', 'barefoot-engine'),
                'sleeps' => __('Sleeps', 'barefoot-engine'),
                'bedroomsMeta' => __('Bedrooms', 'barefoot-engine'),
                'bathroomsMeta' => __('Bathrooms', 'barefoot-engine'),
                'startsAtPrefix' => __('Starts at', 'barefoot-engine'),
                'studio' => __('Studio', 'barefoot-engine'),
            ],
            'items' => $this->normalize_items($this->property_listings_provider->get_active_listings()),
        ];

        $filtered_config = apply_filters('barefoot_engine_property_grid_shortcode_config', $config, $attributes);
        if (is_array($filtered_config)) {
            $config = $filtered_config;
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-property-grid__mount"
                data-be-property-grid
                data-be-property-grid-id="<?php echo esc_attr($instance_id); ?>"
                data-be-property-grid-config="<?php echo esc_attr($config_id); ?>"
            ></div>
            <script id="<?php echo esc_attr($config_id); ?>" type="application/json"><?php echo wp_json_encode($config); ?></script>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param mixed $value
     */
    private function normalize_limit($value): int
    {
        if (!is_numeric($value)) {
            return 9;
        }

        $numeric = (int) $value;
        if ($numeric <= 0) {
            return 9;
        }

        return min(100, $numeric);
    }

    /**
     * @param mixed $value
     */
    private function normalize_positive_int($value, int $default, int $minimum, int $maximum): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $numeric = (int) $value;

        return max($minimum, min($maximum, $numeric));
    }

    /**
     * @param mixed $value
     */
    private function normalize_boolean($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    /**
     * @param mixed $value
     */
    private function normalize_prefilter_bedrooms($value): string
    {
        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return '';
        }

        if ($normalized === 'studio') {
            return 'studio';
        }

        if (str_ends_with($normalized, '+')) {
            $numeric = (int) $normalized;
            return $numeric > 0 ? $numeric . '+' : '';
        }

        if (!is_numeric($normalized)) {
            return '';
        }

        $numeric = (int) $normalized;
        if ($numeric <= 0) {
            return 'studio';
        }

        if ($numeric >= 8) {
            return '8+';
        }

        return (string) $numeric;
    }

    /**
     * @param mixed $value
     */
    private function normalize_prefilter_bathrooms($value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        if (str_ends_with($normalized, '+')) {
            $numeric = (float) $normalized;
            return $numeric > 0 ? rtrim(rtrim((string) $numeric, '0'), '.') . '+' : '';
        }

        if (!is_numeric($normalized)) {
            return sanitize_text_field($normalized);
        }

        $numeric = (float) $normalized;
        if ($numeric >= 6) {
            return '6+';
        }

        $formatted = rtrim(rtrim(number_format($numeric, 1, '.', ''), '0'), '.');
        return $formatted !== '' ? $formatted : '';
    }

    /**
     * @param mixed $value
     */
    private function normalize_prefilter_guests($value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        if (str_ends_with($normalized, '+')) {
            $numeric = (int) $normalized;
            return $numeric > 0 ? $numeric . '+' : '';
        }

        if (!is_numeric($normalized)) {
            return '';
        }

        $numeric = (int) $normalized;
        if ($numeric >= 8) {
            return '8+';
        }

        return $numeric > 0 ? (string) $numeric : '';
    }

    /**
     * @param mixed $value
     */
    private function normalize_text($value, string $default): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $default;
    }

    /**
     * @return array<int, string>
     */
    private function sanitize_class_names(string $classes): array
    {
        $segments = preg_split('/\s+/', trim($classes)) ?: [];
        $normalized = [];

        foreach ($segments as $segment) {
            $class_name = sanitize_html_class($segment);
            if ($class_name !== '') {
                $normalized[] = $class_name;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $listings
     * @return array<int, array<string, mixed>>
     */
    private function normalize_items(array $listings): array
    {
        $items = [];

        foreach ($listings as $listing) {
            if (!is_array($listing)) {
                continue;
            }

            $item = $this->build_item($listing);
            if ($item === null) {
                continue;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $listing
     * @return array<string, mixed>|null
     */
    private function build_item(array $listing): ?array
    {
        $title = $this->sanitize_text($listing['title'] ?? '');
        if ($title === '') {
            return null;
        }

        $search_data = isset($listing['searchData']) && is_array($listing['searchData'])
            ? $listing['searchData']
            : [];
        $field_values = isset($search_data['fields']) && is_array($search_data['fields'])
            ? $search_data['fields']
            : [];
        $filter_values = isset($search_data['filters']) && is_array($search_data['filters'])
            ? $search_data['filters']
            : [];

        $item = [
            'id' => $this->sanitize_text($listing['id'] ?? ''),
            'propertyId' => $this->sanitize_text($listing['propertyId'] ?? ''),
            'title' => $title,
            'permalink' => $this->sanitize_url($listing['permalink'] ?? ''),
            'images' => $this->normalize_image_urls($listing['images'] ?? []),
            'propertyType' => $this->sanitize_text($filter_values['type'] ?? ''),
            'rating' => $this->sanitize_text($filter_values['rating'] ?? ''),
            'guests' => $this->normalize_nullable_int($field_values['guests'] ?? null),
            'bedrooms' => $this->normalize_nullable_int($filter_values['bedrooms'] ?? null),
            'bathrooms' => $this->normalize_nullable_stat($filter_values['bathrooms'] ?? null),
            'price' => $this->normalize_nullable_number($listing['price'] ?? null),
            'pricePeriod' => $this->sanitize_text($listing['pricePeriod'] ?? ''),
        ];

        if (isset($listing['details'])) {
            $details = $this->sanitize_text($listing['details']);
            if ($details !== '') {
                $item['details'] = $details;
            }
        }

        if (isset($listing['badge'])) {
            $badge = $this->sanitize_text($listing['badge']);
            if ($badge !== '') {
                $item['badge'] = $badge;
            }
        }

        return $item;
    }

    /**
     * @param mixed $value
     */
    private function sanitize_text($value): string
    {
        return trim(wp_strip_all_tags((string) $value));
    }

    /**
     * @param mixed $value
     */
    private function sanitize_url($value): string
    {
        $url = esc_url_raw((string) $value);

        return is_string($url) ? $url : '';
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalize_image_urls($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $images = [];

        foreach ($value as $image_url) {
            $sanitized = $this->sanitize_url($image_url);
            if ($sanitized !== '') {
                $images[] = $sanitized;
            }
        }

        return array_values(array_unique($images));
    }

    /**
     * @param mixed $value
     */
    private function normalize_nullable_int($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param mixed $value
     * @return int|float|string|null
     */
    private function normalize_nullable_stat($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            return abs($numeric - floor($numeric)) < 0.00001 ? (int) $numeric : $numeric;
        }

        $normalized = $this->sanitize_text($value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param mixed $value
     */
    private function normalize_nullable_number($value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
