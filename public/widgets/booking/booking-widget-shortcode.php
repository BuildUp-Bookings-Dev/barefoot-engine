<?php

namespace BarefootEngine\Widgets\Booking;

use BarefootEngine\Properties\Property_Booking_Service;
use BarefootEngine\Properties\Property_Post_Type;

if (!defined('ABSPATH')) {
    exit;
}

class Booking_Widget_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_booking_widget';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'widget_id' => 'default',
        'property_id' => '',
        'redirect_url' => '',
        'currency' => '',
        'reztypeid' => '',
        'months_to_show' => '',
        'default_min_days' => '',
        'class' => '',
    ];

    private Booking_Widget_Preset_Registry $preset_registry;
    private Property_Booking_Service $booking_service;

    public function __construct(
        ?Booking_Widget_Preset_Registry $preset_registry = null,
        ?Property_Booking_Service $booking_service = null
    )
    {
        $this->preset_registry = $preset_registry ?? new Booking_Widget_Preset_Registry();
        $this->booking_service = $booking_service ?? new Property_Booking_Service();
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
        $instance_id = wp_unique_id('be-booking-widget-');
        $config_id = $instance_id . '-config';
        $wrapper_classes = ['barefoot-engine-booking-widget', 'barefoot-engine-public'];
        $widget_id = isset($attributes['widget_id']) ? (string) $attributes['widget_id'] : 'default';
        $attribute_property_id = isset($attributes['property_id']) ? trim((string) $attributes['property_id']) : '';

        foreach ($this->sanitize_class_names((string) $attributes['class']) as $class_name) {
            $wrapper_classes[] = $class_name;
        }

        $config = $this->apply_shortcode_overrides(
            $this->preset_registry->get($widget_id),
            $attributes,
            $raw_attributes
        );

        $resolved_property_id = $this->resolve_property_id($attribute_property_id);
        $config['propertyId'] = $resolved_property_id;

        $initial_calendar = $this->build_initial_calendar_context($resolved_property_id, $config);
        if ($initial_calendar !== []) {
            $config['initialCalendar'] = $initial_calendar;
        }

        if ($resolved_property_id === '') {
            $config['missingContext'] = true;
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-booking-widget__mount"
                data-be-booking-widget
                data-be-booking-widget-id="<?php echo esc_attr($instance_id); ?>"
                data-be-booking-widget-config="<?php echo esc_attr($config_id); ?>"
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
            $currency = trim(sanitize_text_field((string) $attributes['currency']));
            if ($currency !== '') {
                $config['currency'] = $currency;
            }
        }

        if (array_key_exists('redirect_url', $raw_attributes)) {
            $redirect_url = $this->sanitize_redirect_url((string) $attributes['redirect_url']);
            if ($redirect_url !== '') {
                $config['redirectUrl'] = $redirect_url;
            }
        }

        if (array_key_exists('reztypeid', $raw_attributes) && is_numeric($attributes['reztypeid'])) {
            $reztypeid = (int) $attributes['reztypeid'];
            if ($reztypeid > 0) {
                $config['reztypeid'] = $reztypeid;
            }
        }

        if (array_key_exists('months_to_show', $raw_attributes) && is_numeric($attributes['months_to_show'])) {
            $months = (int) $attributes['months_to_show'];
            $config['calendarOptions']['monthsToShow'] = max(1, min(6, $months));
        }

        if (array_key_exists('default_min_days', $raw_attributes) && is_numeric($attributes['default_min_days'])) {
            $days = (int) $attributes['default_min_days'];
            $config['calendarOptions']['defaultMinDays'] = max(1, $days);
        }

        return $config;
    }

    private function resolve_property_id(string $attribute_property_id): string
    {
        $normalized_attribute_property_id = trim(sanitize_text_field($attribute_property_id));
        if ($normalized_attribute_property_id !== '') {
            return $normalized_attribute_property_id;
        }

        if (!is_singular(Property_Post_Type::POST_TYPE)) {
            return '';
        }

        $post_id = get_queried_object_id();
        if (!is_numeric($post_id) || (int) $post_id <= 0) {
            return '';
        }

        $property_id = get_post_meta((int) $post_id, '_be_property_id', true);
        if (!is_scalar($property_id)) {
            return '';
        }

        return trim(sanitize_text_field((string) $property_id));
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function build_initial_calendar_context(string $property_id, array $config): array
    {
        if ($property_id === '') {
            return [];
        }

        $range = $this->resolve_initial_calendar_range($config);
        if ($range === null) {
            return [];
        }

        $daily_prices = $this->booking_service->get_daily_prices_for_range(
            $property_id,
            $range['month_start'],
            $range['month_end']
        );
        if (is_wp_error($daily_prices) || $daily_prices === []) {
            return [];
        }

        return [
            'monthStart' => $range['month_start'],
            'monthEnd' => $range['month_end'],
            'dailyPrices' => $daily_prices,
        ];
    }

    /**
     * @param array<string, mixed> $config
     * @return array{month_start: string, month_end: string}|null
     */
    private function resolve_initial_calendar_range(array $config): ?array
    {
        $months_to_show = isset($config['calendarOptions']['monthsToShow']) && is_numeric($config['calendarOptions']['monthsToShow'])
            ? (int) $config['calendarOptions']['monthsToShow']
            : 2;
        $months_to_show = max(1, min(6, $months_to_show));
        $seed_date = $this->read_initial_calendar_seed_date();
        $seed = \DateTimeImmutable::createFromFormat('!Y-m-d', $seed_date, wp_timezone());
        if (!$seed instanceof \DateTimeImmutable) {
            return null;
        }

        $month_start = $seed->modify('first day of this month');
        $month_end = $month_start
            ->modify('+' . ($months_to_show - 1) . ' months')
            ->modify('last day of this month');

        return [
            'month_start' => $month_start->format('Y-m-d'),
            'month_end' => $month_end->format('Y-m-d'),
        ];
    }

    private function read_initial_calendar_seed_date(): string
    {
        $raw_check_in = '';
        if (isset($_GET['check_in'])) {
            $raw_check_in = sanitize_text_field(wp_unslash((string) $_GET['check_in']));
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_check_in) === 1 && strtotime($raw_check_in) !== false) {
            return $raw_check_in;
        }

        return wp_date('Y-m-d');
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

    private function sanitize_redirect_url(string $url): string
    {
        $normalized_url = trim($url);
        if ($normalized_url === '') {
            return '';
        }

        if (strpos($normalized_url, '/') === 0) {
            return $normalized_url;
        }

        return esc_url_raw($normalized_url);
    }
}
