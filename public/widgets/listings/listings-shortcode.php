<?php

namespace BarefootEngine\Widgets\Listings;

use BarefootEngine\Properties\Property_Post_Type;
use BarefootEngine\Properties\Property_Taxonomies;
use BarefootEngine\Properties\Property_Listings_Provider;
use BarefootEngine\Widgets\Search\Search_Widget_Preset_Registry;

if (!defined('ABSPATH')) {
    exit;
}

class Listings_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_listings';

    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'widget_id' => 'default',
        'search_widget_id' => '',
        'currency' => '$',
        'center_lat' => '14.55',
        'center_lng' => '121.03',
        'zoom' => '12',
        'show_map_toggle' => 'true',
        'show_sort' => 'true',
        'show_pagination' => 'true',
        'page_size' => '12',
        'height' => '720px',
        'class' => '',
    ];

    private Listings_Preset_Registry $preset_registry;
    private Property_Listings_Provider $property_listings_provider;
    private Search_Widget_Preset_Registry $search_widget_preset_registry;

    public function __construct(
        ?Listings_Preset_Registry $preset_registry = null,
        ?Property_Listings_Provider $property_listings_provider = null,
        ?Search_Widget_Preset_Registry $search_widget_preset_registry = null
    )
    {
        $this->preset_registry = $preset_registry ?? new Listings_Preset_Registry();
        $this->property_listings_provider = $property_listings_provider ?? new Property_Listings_Provider();
        $this->search_widget_preset_registry = $search_widget_preset_registry ?? new Search_Widget_Preset_Registry();
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
        $raw_attributes = is_array($atts) ? $atts : [];
        $attributes = shortcode_atts(self::DEFAULTS, $atts, self::SHORTCODE_TAG);
        $instance_id = wp_unique_id('be-listings-');
        $config_id = $instance_id . '-config';
        $wrapper_classes = ['barefoot-engine-listings', 'barefoot-engine-public'];
        $height = $this->sanitize_dimension((string) $attributes['height'], '720px');
        $widget_id = isset($attributes['widget_id']) ? (string) $attributes['widget_id'] : 'default';
        $search_widget_id = isset($attributes['search_widget_id']) ? sanitize_title_with_dashes((string) $attributes['search_widget_id']) : '';

        foreach ($this->sanitize_class_names((string) $attributes['class']) as $class_name) {
            $wrapper_classes[] = $class_name;
        }

        $config = $this->apply_shortcode_overrides(
            $this->preset_registry->get($widget_id),
            $attributes,
            $raw_attributes
        );
        $config['listings'] = $this->property_listings_provider->get_active_listings();

        if ($search_widget_id !== '' && array_key_exists('search_widget_id', $raw_attributes)) {
            $config['searchWidget'] = $this->normalize_search_widget_config(
                $this->search_widget_preset_registry->get($search_widget_id)
            );
        }
        $config = $this->apply_runtime_search_widget_options($config);

        $filtered_config = apply_filters('barefoot_engine_listings_shortcode_config', $config, $attributes);
        if (is_array($filtered_config)) {
            $config = $this->normalize_filtered_config($filtered_config, $config);
        }

        $mount_style = '';
        if (empty($config['fullHeightMap'])) {
            $mount_style = 'height:' . $height;
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-listings__mount"
                data-be-listings
                data-be-listings-id="<?php echo esc_attr($instance_id); ?>"
                data-be-listings-config="<?php echo esc_attr($config_id); ?>"
                <?php if ($mount_style !== '') : ?>
                    style="<?php echo esc_attr($mount_style); ?>"
                <?php endif; ?>
            ></div>
            <script id="<?php echo esc_attr($config_id); ?>" type="application/json"><?php echo wp_json_encode($config); ?></script>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $raw_attributes
     * @return array<string, mixed>
     */
    private function apply_shortcode_overrides(array $config, array $attributes, array $raw_attributes): array
    {
        if (array_key_exists('currency', $raw_attributes)) {
            $config['currency'] = $this->sanitize_currency((string) $attributes['currency']);
        }

        if (array_key_exists('center_lat', $raw_attributes)) {
            $config['mapOptions']['center'][0] = $this->normalize_coordinate($attributes['center_lat'], (float) $config['mapOptions']['center'][0], -90, 90);
        }

        if (array_key_exists('center_lng', $raw_attributes)) {
            $config['mapOptions']['center'][1] = $this->normalize_coordinate($attributes['center_lng'], (float) $config['mapOptions']['center'][1], -180, 180);
        }

        if (array_key_exists('zoom', $raw_attributes)) {
            $config['mapOptions']['zoom'] = $this->normalize_integer($attributes['zoom'], (int) $config['mapOptions']['zoom'], 1, 20);
        }

        if (array_key_exists('show_map_toggle', $raw_attributes)) {
            $config['showMapToggle'] = $this->normalize_boolean($attributes['show_map_toggle'], true);
        }

        if (array_key_exists('show_sort', $raw_attributes)) {
            $config['showSort'] = $this->normalize_boolean($attributes['show_sort'], true);
        }

        if (array_key_exists('show_pagination', $raw_attributes)) {
            $config['showPagination'] = $this->normalize_boolean($attributes['show_pagination'], true);
        }

        if (array_key_exists('page_size', $raw_attributes)) {
            $config['pageSize'] = $this->normalize_page_size($attributes['page_size']);
        }

        return $config;
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
     * @param mixed $value
     */
    private function normalize_boolean($value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (!is_string($value)) {
            return $default;
        }

        $normalized = strtolower(trim($value));
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
    private function normalize_coordinate($value, float $default, float $min, float $max): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $numeric = (float) $value;

        return max($min, min($max, $numeric));
    }

    /**
     * @param mixed $value
     */
    private function normalize_integer($value, int $default, int $min, int $max): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $numeric = (int) $value;

        return max($min, min($max, $numeric));
    }

    /**
     * @param mixed $value
     */
    private function normalize_page_size($value): int
    {
        if (!is_numeric($value)) {
            return 12;
        }

        $numeric = (int) $value;
        if ($numeric <= 0) {
            return 0;
        }

        return min(100, $numeric);
    }

    /**
     * @param mixed $value
     */
    private function normalize_pagination_mode($value, string $default): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $mode = strtolower(trim((string) $value));

        return in_array($mode, ['pages', 'infinite'], true) ? $mode : $default;
    }

    /**
     * @param mixed $value
     * @param mixed $default
     * @return array<int, float>|null
     */
    private function normalize_marker_focus_center($value, $default): ?array
    {
        $candidate = is_array($value) ? $value : $default;

        if (!is_array($candidate) || count($candidate) < 2) {
            return null;
        }

        return [
            $this->normalize_coordinate($candidate[0], 14.55, -90, 90),
            $this->normalize_coordinate($candidate[1], 121.03, -180, 180),
        ];
    }

    private function sanitize_currency(string $value): string
    {
        $currency = trim(sanitize_text_field($value));

        return $currency !== '' ? $currency : '$';
    }

    private function sanitize_dimension(string $value, string $default): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return $default;
        }

        if (preg_match('/^\d+(\.\d+)?(px|%|vh|vw|rem|em)$/', $normalized) !== 1) {
            return $default;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $filtered_config
     * @param array<string, mixed> $fallback_config
     * @return array<string, mixed>
     */
    private function normalize_filtered_config(array $filtered_config, array $fallback_config): array
    {
        $config = $fallback_config;

        if (isset($filtered_config['listings']) && is_array($filtered_config['listings'])) {
            $config['listings'] = array_values(
                array_filter(
                    $filtered_config['listings'],
                    static fn($item): bool => is_array($item)
                )
            );
        }

        if (isset($filtered_config['currency']) && is_string($filtered_config['currency'])) {
            $config['currency'] = $this->sanitize_currency($filtered_config['currency']);
        }

        if (isset($filtered_config['showMapToggle'])) {
            $config['showMapToggle'] = $this->normalize_boolean($filtered_config['showMapToggle'], (bool) $fallback_config['showMapToggle']);
        }

        if (isset($filtered_config['showSort'])) {
            $config['showSort'] = $this->normalize_boolean($filtered_config['showSort'], (bool) $fallback_config['showSort']);
        }

        if (isset($filtered_config['showPagination'])) {
            $config['showPagination'] = $this->normalize_boolean($filtered_config['showPagination'], (bool) $fallback_config['showPagination']);
        }

        if (isset($filtered_config['pageSize'])) {
            $config['pageSize'] = $this->normalize_page_size($filtered_config['pageSize']);
        }

        if (isset($filtered_config['paginationMode'])) {
            $config['paginationMode'] = $this->normalize_pagination_mode(
                $filtered_config['paginationMode'],
                (string) ($fallback_config['paginationMode'] ?? 'pages')
            );
        }

        if (isset($filtered_config['stickyMap'])) {
            $config['stickyMap'] = $this->normalize_boolean(
                $filtered_config['stickyMap'],
                (bool) ($fallback_config['stickyMap'] ?? false)
            );
        }

        if (isset($filtered_config['fullHeightMap'])) {
            $config['fullHeightMap'] = $this->normalize_boolean(
                $filtered_config['fullHeightMap'],
                (bool) ($fallback_config['fullHeightMap'] ?? false)
            );
        }

        if (isset($filtered_config['minDesktopColumns'])) {
            $config['minDesktopColumns'] = $this->normalize_integer(
                $filtered_config['minDesktopColumns'],
                (int) ($fallback_config['minDesktopColumns'] ?? 3),
                1,
                12
            );
        }

        if (isset($filtered_config['maxDesktopColumns'])) {
            $config['maxDesktopColumns'] = $this->normalize_integer(
                $filtered_config['maxDesktopColumns'],
                (int) ($fallback_config['maxDesktopColumns'] ?? 8),
                1,
                12
            );
        }

        if (isset($filtered_config['markerFocusZoom'])) {
            $config['markerFocusZoom'] = $this->normalize_integer(
                $filtered_config['markerFocusZoom'],
                (int) ($fallback_config['markerFocusZoom'] ?? 15),
                1,
                19
            );
        }

        if (array_key_exists('markerFocusCenter', $filtered_config)) {
            $config['markerFocusCenter'] = $this->normalize_marker_focus_center(
                $filtered_config['markerFocusCenter'],
                $fallback_config['markerFocusCenter'] ?? null
            );
        }

        if (isset($filtered_config['mapOptions']) && is_array($filtered_config['mapOptions'])) {
            $center = $filtered_config['mapOptions']['center'] ?? null;
            if (is_array($center) && count($center) >= 2) {
                $config['mapOptions']['center'] = [
                    $this->normalize_coordinate($center[0], (float) $fallback_config['mapOptions']['center'][0], -90, 90),
                    $this->normalize_coordinate($center[1], (float) $fallback_config['mapOptions']['center'][1], -180, 180),
                ];
            }

            if (isset($filtered_config['mapOptions']['zoom'])) {
                $config['mapOptions']['zoom'] = $this->normalize_integer(
                    $filtered_config['mapOptions']['zoom'],
                    (int) $fallback_config['mapOptions']['zoom'],
                    1,
                    20
                );
            }
        }

        if (isset($filtered_config['searchWidget']) && is_array($filtered_config['searchWidget'])) {
            $config['searchWidget'] = $this->normalize_search_widget_config($filtered_config['searchWidget']);
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalize_search_widget_config(array $config): array
    {
        $target_url = isset($config['targetUrl']) && is_string($config['targetUrl'])
            ? trim($config['targetUrl'])
            : '';

        if ($target_url === '') {
            $target_url = get_permalink() ?: '';
        }

        $config['targetUrl'] = $target_url;

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function apply_runtime_search_widget_options(array $config): array
    {
        if (!isset($config['searchWidget']) || !is_array($config['searchWidget'])) {
            return $config;
        }

        $search_widget = $config['searchWidget'];
        $fields = isset($search_widget['fields']) && is_array($search_widget['fields'])
            ? array_values(array_filter($search_widget['fields'], static fn($item): bool => is_array($item)))
            : [];
        $filters = isset($search_widget['filters']) && is_array($search_widget['filters'])
            ? array_values(array_filter($search_widget['filters'], static fn($item): bool => is_array($item)))
            : [];

        $next_fields = [];
        foreach ($fields as $field) {
            $field['required'] = false;
            $next_fields[] = $field;
        }

        $amenity_options = $this->get_taxonomy_term_options(Property_Taxonomies::AMENITY_TAXONOMY);
        $type_options = $this->get_taxonomy_term_options(Property_Taxonomies::TYPE_TAXONOMY);
        $rating_options = $this->get_rating_options();

        $dynamic_filter_map = [
            'amenities' => [
                'label' => __('Amenities', 'barefoot-engine'),
                'type' => 'checkbox',
                'options' => $amenity_options,
            ],
            'type' => [
                'label' => __('Type', 'barefoot-engine'),
                'type' => 'select',
                'options' => $type_options,
            ],
            'rating' => [
                'label' => __('Rating', 'barefoot-engine'),
                'type' => 'select',
                'options' => $rating_options,
            ],
        ];

        $next_filters = [];
        $seen_keys = [];

        foreach ($filters as $filter) {
            $key = isset($filter['key']) && is_scalar($filter['key']) ? trim((string) $filter['key']) : '';
            if ($key === '') {
                continue;
            }

            if (isset($dynamic_filter_map[$key])) {
                $dynamic = $dynamic_filter_map[$key];
                $options = $dynamic['options'];
                if (!is_array($options) || $options === []) {
                    $seen_keys[$key] = true;
                    continue;
                }

                $filter['label'] = $dynamic['label'];
                $filter['type'] = $dynamic['type'];
                $filter['options'] = $options;
            }

            $filter['required'] = false;
            $next_filters[] = $filter;
            $seen_keys[$key] = true;
        }

        foreach ($dynamic_filter_map as $key => $dynamic) {
            if (isset($seen_keys[$key])) {
                continue;
            }

            $options = $dynamic['options'];
            if (!is_array($options) || $options === []) {
                continue;
            }

            $next_filters[] = [
                'label' => $dynamic['label'],
                'type' => $dynamic['type'],
                'options' => $options,
                'required' => false,
                'key' => $key,
            ];
        }

        $search_widget['fields'] = $next_fields;
        $search_widget['filters'] = $this->prioritize_filter_order($next_filters);
        $config['searchWidget'] = $search_widget;

        return $config;
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     * @return array<int, array<string, mixed>>
     */
    private function prioritize_filter_order(array $filters): array
    {
        $priority_keys = ['rating', 'type'];
        $prioritized = [];
        $used_indexes = [];

        foreach ($priority_keys as $priority_key) {
            foreach ($filters as $index => $filter) {
                $key = isset($filter['key']) && is_scalar($filter['key']) ? trim((string) $filter['key']) : '';
                if ($key === '' || $key !== $priority_key || isset($used_indexes[$index])) {
                    continue;
                }

                $prioritized[] = $filter;
                $used_indexes[$index] = true;
            }
        }

        foreach ($filters as $index => $filter) {
            if (isset($used_indexes[$index])) {
                continue;
            }

            $prioritized[] = $filter;
        }

        return $prioritized;
    }

    /**
     * @return array<int, string>
     */
    private function get_taxonomy_term_options(string $taxonomy): array
    {
        if (!taxonomy_exists($taxonomy)) {
            return [];
        }

        $terms = get_terms(
            [
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
                'fields' => 'names',
                'orderby' => 'name',
                'order' => 'ASC',
            ]
        );

        if (!is_array($terms) || is_wp_error($terms)) {
            return [];
        }

        $options = [];
        foreach ($terms as $term_name) {
            if (!is_scalar($term_name)) {
                continue;
            }

            $name = trim((string) $term_name);
            if ($name !== '') {
                $options[] = $name;
            }
        }

        return array_values(array_unique($options));
    }

    /**
     * @return array<int, string>
     */
    private function get_rating_options(): array
    {
        $post_ids = get_posts(
            [
                'post_type' => Property_Post_Type::POST_TYPE,
                'post_status' => 'publish',
                'meta_key' => '_be_property_import_status',
                'meta_value' => 'active',
                'numberposts' => -1,
                'fields' => 'ids',
            ]
        );

        if (!is_array($post_ids) || $post_ids === []) {
            return [];
        }

        $ratings = [];

        foreach ($post_ids as $post_id) {
            $rating = trim((string) get_post_meta((int) $post_id, '_be_property_api_a267', true));
            if ($rating === '') {
                continue;
            }

            $ratings[] = sanitize_text_field($rating);
        }

        $ratings = array_values(array_unique(array_filter($ratings, static fn(string $value): bool => $value !== '')));
        sort($ratings, SORT_NATURAL | SORT_FLAG_CASE);

        return $ratings;
    }
}
