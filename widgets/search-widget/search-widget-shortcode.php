<?php

namespace BarefootEngine\Widgets\Search;

if (!defined('ABSPATH')) {
    exit;
}

class Search_Widget_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_search_widget';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'widget_id' => 'default',
        'target_url' => '',
        'show_location' => 'true',
        'show_filter_button' => 'false',
        'location_label' => 'Location',
        'location_placeholder' => 'Where are you going?',
        'date_label' => 'Dates',
        'date_placeholder' => 'Check in — Check out',
        'months_to_show' => '1',
        'datepicker_placement' => 'auto',
        'class' => '',
    ];

    private Search_Widget_Preset_Registry $preset_registry;

    public function __construct(?Search_Widget_Preset_Registry $preset_registry = null)
    {
        $this->preset_registry = $preset_registry ?? new Search_Widget_Preset_Registry();
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
        $instance_id = wp_unique_id('be-search-widget-');
        $config_id = $instance_id . '-config';
        $wrapper_classes = ['barefoot-engine-search-widget', 'barefoot-engine-public'];
        $widget_id = isset($attributes['widget_id']) ? (string) $attributes['widget_id'] : 'default';

        foreach ($this->sanitize_class_names((string) $attributes['class']) as $class_name) {
            $wrapper_classes[] = $class_name;
        }

        $config = $this->apply_shortcode_overrides(
            $this->preset_registry->get($widget_id),
            $attributes,
            $raw_attributes
        );

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-search-widget__mount"
                data-be-search-widget
                data-be-search-widget-id="<?php echo esc_attr($instance_id); ?>"
                data-be-search-widget-config="<?php echo esc_attr($config_id); ?>"
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
        if (array_key_exists('target_url', $raw_attributes)) {
            $config['targetUrl'] = $this->sanitize_target_url((string) $attributes['target_url']);
        }

        if (array_key_exists('show_location', $raw_attributes)) {
            $config['showLocation'] = $this->normalize_boolean($attributes['show_location'], true);
        }

        if (array_key_exists('show_filter_button', $raw_attributes)) {
            $config['showFilterButton'] = $this->normalize_boolean($attributes['show_filter_button'], false);
        }

        if (array_key_exists('location_label', $raw_attributes)) {
            $config['locationLabel'] = $this->sanitize_text((string) $attributes['location_label']);
        }

        if (array_key_exists('location_placeholder', $raw_attributes)) {
            $config['locationPlaceholder'] = $this->sanitize_text((string) $attributes['location_placeholder']);
        }

        if (array_key_exists('date_label', $raw_attributes)) {
            $config['dateLabel'] = $this->sanitize_text((string) $attributes['date_label']);
        }

        if (array_key_exists('date_placeholder', $raw_attributes)) {
            $config['datePlaceholder'] = $this->sanitize_text((string) $attributes['date_placeholder']);
        }

        if (array_key_exists('months_to_show', $raw_attributes)) {
            $config['calendarOptions']['monthsToShow'] = $this->normalize_months_to_show($attributes['months_to_show']);
        }

        if (array_key_exists('datepicker_placement', $raw_attributes)) {
            $config['calendarOptions']['datepickerPlacement'] = $this->sanitize_text((string) $attributes['datepicker_placement']);
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
    private function normalize_months_to_show($value): int
    {
        $months = is_numeric($value) ? (int) $value : 1;

        return max(1, min(6, $months));
    }

    private function sanitize_target_url(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        return esc_url_raw($url);
    }

    private function sanitize_text(string $value): string
    {
        return sanitize_text_field($value);
    }
}
