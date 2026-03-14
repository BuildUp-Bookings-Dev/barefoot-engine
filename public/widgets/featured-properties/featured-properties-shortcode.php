<?php

namespace BarefootEngine\Widgets\FeaturedProperties;

use BarefootEngine\Properties\Property_Listings_Provider;

if (!defined('ABSPATH')) {
    exit;
}

class Featured_Properties_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_featured_properties';
    /**
     * @var array<int, string>
     */
    private const ALLOWED_META_DISPLAY = [
        'starts_at',
        'property_type',
        'view',
        'sleeps',
        'bedrooms',
        'bathrooms',
    ];

    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'limit' => '9',
        'class' => '',
        'title' => '',
        'empty_text' => '',
        'currency' => '$',
        'starts_at_prefix' => '',
        'meta_display' => 'starts_at,property_type,view,sleeps,bedrooms,bathrooms',
        'slider_controls_position' => 'top-right',
        'heading_position' => 'left',
        'outer_loop' => 'true',
        'outer_navigation' => 'true',
        'outer_autoplay' => 'false',
        'outer_autoplay_delay' => '5000',
        'slides_mobile' => '1',
        'slides_tablet' => '2',
        'slides_desktop' => '3',
        'gap_mobile' => '16',
        'gap_tablet' => '20',
        'gap_desktop' => '24',
        'inner_loop' => 'true',
        'inner_navigation' => 'true',
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
        $instance_id = wp_unique_id('be-featured-properties-');
        $config_id = $instance_id . '-config';
        $limit = $this->normalize_limit($attributes['limit'] ?? self::DEFAULTS['limit']);
        $meta_display = $this->normalize_meta_display($attributes['meta_display'] ?? self::DEFAULTS['meta_display']);
        $wrapper_classes = ['barefoot-engine-featured-properties', 'barefoot-engine-public'];
        $default_starts_at_prefix = __('Starts at', 'barefoot-engine');

        foreach ($this->sanitize_class_names((string) $attributes['class']) as $class_name) {
            $wrapper_classes[] = $class_name;
        }

        $config = [
            'title' => $this->normalize_text($attributes['title'] ?? '', __('Featured Properties', 'barefoot-engine')),
            'currency' => $this->normalize_text($attributes['currency'] ?? '', '$'),
            'limit' => $limit,
            'metaDisplay' => $meta_display,
            'sliderControlPosition' => $this->normalize_slider_controls_position($attributes['slider_controls_position'] ?? ''),
            'headingPosition' => $this->normalize_heading_position($attributes['heading_position'] ?? ''),
            'items' => $this->property_listings_provider->get_featured_properties($limit),
            'emptyText' => $this->normalize_text(
                $attributes['empty_text'] ?? '',
                __('No featured properties available yet.', 'barefoot-engine')
            ),
            'slider' => [
                'outer' => [
                    'loop' => $this->normalize_boolean($attributes['outer_loop'] ?? '', true),
                    'navigation' => $this->normalize_boolean($attributes['outer_navigation'] ?? '', true),
                    'autoplay' => $this->normalize_boolean($attributes['outer_autoplay'] ?? '', false),
                    'autoplayDelay' => $this->normalize_positive_int(
                        $attributes['outer_autoplay_delay'] ?? '',
                        5000,
                        1000,
                        30000
                    ),
                    'slidesPerView' => [
                        'mobile' => $this->normalize_positive_int($attributes['slides_mobile'] ?? '', 1, 1, 2),
                        'tablet' => $this->normalize_positive_int($attributes['slides_tablet'] ?? '', 2, 1, 3),
                        'desktop' => $this->normalize_positive_int($attributes['slides_desktop'] ?? '', 3, 1, 6),
                    ],
                    'spaceBetween' => [
                        'mobile' => $this->normalize_positive_int($attributes['gap_mobile'] ?? '', 16, 0, 80),
                        'tablet' => $this->normalize_positive_int($attributes['gap_tablet'] ?? '', 20, 0, 80),
                        'desktop' => $this->normalize_positive_int($attributes['gap_desktop'] ?? '', 24, 0, 120),
                    ],
                ],
                'inner' => [
                    'loop' => $this->normalize_boolean($attributes['inner_loop'] ?? '', true),
                    'navigation' => $this->normalize_boolean($attributes['inner_navigation'] ?? '', true),
                ],
            ],
            'labels' => [
                'propertyType' => __('Type', 'barefoot-engine'),
                'view' => __('View', 'barefoot-engine'),
                'sleeps' => __('Sleeps', 'barefoot-engine'),
                'bedrooms' => __('Bedrooms', 'barefoot-engine'),
                'bathrooms' => __('Bathrooms', 'barefoot-engine'),
                'startsAtPrefix' => $this->normalize_text($attributes['starts_at_prefix'] ?? '', $default_starts_at_prefix),
                'previous' => __('Previous', 'barefoot-engine'),
                'next' => __('Next', 'barefoot-engine'),
            ],
        ];

        $filtered_config = apply_filters('barefoot_engine_featured_properties_shortcode_config', $config, $attributes);
        if (is_array($filtered_config)) {
            $config = $filtered_config;
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-featured-properties__mount"
                data-be-featured-properties
                data-be-featured-properties-id="<?php echo esc_attr($instance_id); ?>"
                data-be-featured-properties-config="<?php echo esc_attr($config_id); ?>"
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

        return min(30, $numeric);
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
    private function normalize_text($value, string $default): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $default;
    }

    /**
     * @param mixed $value
     */
    private function normalize_heading_position($value): string
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['left', 'center', 'right'], true)) {
            return $normalized;
        }

        return 'left';
    }

    /**
     * @param mixed $value
     */
    private function normalize_slider_controls_position($value): string
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['side', 'top-right', 'bottom-center'], true)) {
            return $normalized;
        }

        return 'top-right';
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalize_meta_display($value): array
    {
        if (is_array($value)) {
            $segments = $value;
        } else {
            $segments = preg_split('/\s*,\s*/', trim((string) $value)) ?: [];
        }

        $normalized = [];
        foreach ($segments as $segment) {
            $key = strtolower(trim((string) $segment));
            if ($key === '' || !in_array($key, self::ALLOWED_META_DISPLAY, true)) {
                continue;
            }

            if (!in_array($key, $normalized, true)) {
                $normalized[] = $key;
            }
        }

        if (!empty($normalized)) {
            return $normalized;
        }

        return self::ALLOWED_META_DISPLAY;
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
}
