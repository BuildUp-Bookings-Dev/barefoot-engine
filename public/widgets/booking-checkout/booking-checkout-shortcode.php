<?php

namespace BarefootEngine\Widgets\BookingCheckout;

use BarefootEngine\Properties\Property_Booking_Checkout_Service;
use BarefootEngine\Properties\Property_Post_Type;

if (!defined('ABSPATH')) {
    exit;
}

class Booking_Checkout_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_booking_checkout';

    /**
     * @var array<string, mixed>
     */
    private const DEFAULTS = [
        'widget_id' => 'default',
        'property_id' => '',
        'currency' => '',
        'reztypeid' => '',
        'class' => '',
    ];

    private Booking_Checkout_Preset_Registry $preset_registry;
    private Property_Booking_Checkout_Service $checkout_service;

    public function __construct(
        ?Booking_Checkout_Preset_Registry $preset_registry = null,
        ?Property_Booking_Checkout_Service $checkout_service = null
    ) {
        $this->preset_registry = $preset_registry ?? new Booking_Checkout_Preset_Registry();
        $this->checkout_service = $checkout_service ?? new Property_Booking_Checkout_Service();
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
        $instance_id = wp_unique_id('be-booking-checkout-');
        $config_id = $instance_id . '-config';
        $wrapper_classes = ['barefoot-engine-booking-checkout', 'barefoot-engine-public'];
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

        if ($resolved_property_id === '') {
            $config['missingContext'] = true;
        } else {
            $property_summary = $this->checkout_service->get_property_summary($resolved_property_id);
            if (is_array($property_summary)) {
                $config['propertySummary'] = $property_summary;
            } else {
                $config['missingContext'] = true;
            }
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-booking-checkout__mount"
                data-be-booking-checkout
                data-be-booking-checkout-id="<?php echo esc_attr($instance_id); ?>"
                data-be-booking-checkout-config="<?php echo esc_attr($config_id); ?>"
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

        if (array_key_exists('reztypeid', $raw_attributes) && is_numeric($attributes['reztypeid'])) {
            $reztypeid = (int) $attributes['reztypeid'];
            if ($reztypeid > 0) {
                $config['reztypeid'] = $reztypeid;
            }
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
