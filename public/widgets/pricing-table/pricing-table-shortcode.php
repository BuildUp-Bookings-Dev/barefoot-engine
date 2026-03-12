<?php

namespace BarefootEngine\Widgets\Pricing;

use BarefootEngine\Properties\Property_Post_Type;

if (!defined('ABSPATH')) {
    exit;
}

class Pricing_Table_Shortcode
{
    public const SHORTCODE_TAG = 'barefoot_pricing_table';

    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        'property_id' => '',
        'title' => 'Rates',
        'currency' => '$',
        'show_search' => 'true',
        'search_placeholder' => 'Search rates...',
        'empty_text' => 'No rates available for this property yet.',
        'class' => '',
    ];

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
        $property_id_attribute = isset($attributes['property_id']) ? (string) $attributes['property_id'] : '';
        $resolved_post_id = $this->resolve_property_post_id($property_id_attribute);
        $rates = $this->load_property_rates($resolved_post_id);
        $currency = $this->sanitize_currency((string) ($attributes['currency'] ?? '$'));
        $instance_id = wp_unique_id('be-pricing-table-');
        $config_id = $instance_id . '-config';
        $wrapper_classes = ['barefoot-engine-pricing-table', 'barefoot-engine-public'];
        $config = [
            'title' => $this->sanitize_text((string) ($attributes['title'] ?? 'Rates'), 'Rates'),
            'showSearch' => $this->normalize_boolean($attributes['show_search'] ?? 'true'),
            'searchPlaceholder' => $this->sanitize_text((string) ($attributes['search_placeholder'] ?? ''), 'Search rates...'),
            'emptyText' => $this->sanitize_text((string) ($attributes['empty_text'] ?? ''), 'No rates available for this property yet.'),
            'columns' => [
                'dateRange' => __('Date Range', 'barefoot-engine'),
                'daily' => __('Daily', 'barefoot-engine'),
                'weekly' => __('Weekly', 'barefoot-engine'),
                'monthly' => __('Monthly', 'barefoot-engine'),
            ],
            'rows' => $this->build_table_rows($rates, $currency),
            'sort' => [
                'key' => 'date_start',
                'direction' => 'asc',
            ],
        ];

        foreach ($this->sanitize_class_names((string) ($attributes['class'] ?? '')) as $class_name) {
            $wrapper_classes[] = $class_name;
        }

        if ($resolved_post_id > 0) {
            $property_id_meta = get_post_meta($resolved_post_id, '_be_property_id', true);
            if (is_scalar($property_id_meta)) {
                $config['propertyId'] = trim(sanitize_text_field((string) $property_id_meta));
            }
        } elseif (array_key_exists('property_id', $raw_attributes)) {
            $config['propertyId'] = trim(sanitize_text_field($property_id_attribute));
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', array_unique($wrapper_classes))); ?>">
            <div
                id="<?php echo esc_attr($instance_id); ?>"
                class="barefoot-engine-pricing-table__mount"
                data-be-pricing-table
                data-be-pricing-table-id="<?php echo esc_attr($instance_id); ?>"
                data-be-pricing-table-config="<?php echo esc_attr($config_id); ?>"
            ></div>
            <script id="<?php echo esc_attr($config_id); ?>" type="application/json"><?php echo wp_json_encode($config); ?></script>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function resolve_property_post_id(string $property_id): int
    {
        $normalized_property_id = trim(sanitize_text_field($property_id));
        if ($normalized_property_id !== '') {
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
                            'value' => $normalized_property_id,
                            'compare' => '=',
                        ],
                    ],
                ]
            );

            if (is_array($query->posts) && !empty($query->posts)) {
                return (int) $query->posts[0];
            }
        }

        if (is_singular(Property_Post_Type::POST_TYPE)) {
            $post_id = get_queried_object_id();
            if (is_numeric($post_id) && (int) $post_id > 0) {
                return (int) $post_id;
            }
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function load_property_rates(int $post_id): array
    {
        if ($post_id <= 0) {
            return [];
        }

        $rates = get_post_meta($post_id, '_be_property_rates', true);

        return is_array($rates) ? $rates : [];
    }

    /**
     * @param array<string, mixed> $rates
     * @return array<int, array<string, mixed>>
     */
    private function build_table_rows(array $rates, string $currency): array
    {
        $items = isset($rates['items']) && is_array($rates['items']) ? $rates['items'] : [];
        $grouped_rows = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $date_start = $this->normalize_rate_date($item['date_start'] ?? $item['date1'] ?? '');
            $date_end = $this->normalize_rate_date($item['date_end'] ?? $item['date2'] ?? '');
            if ($date_start === '' || $date_end === '') {
                continue;
            }

            $price_type = isset($item['pricetype']) && is_scalar($item['pricetype'])
                ? strtolower(trim((string) $item['pricetype']))
                : '';
            if (!in_array($price_type, ['daily', 'weekly', 'monthly'], true)) {
                continue;
            }

            $amount = $this->normalize_rate_amount($item['amount'] ?? $item['rent'] ?? null);
            if ($amount === null) {
                continue;
            }

            $row_key = $date_start . '|' . $date_end;

            if (!isset($grouped_rows[$row_key]) || !is_array($grouped_rows[$row_key])) {
                $grouped_rows[$row_key] = [
                    'date_start' => $date_start,
                    'date_end' => $date_end,
                    'date_range' => $this->format_date_range($date_start, $date_end),
                    'daily' => null,
                    'weekly' => null,
                    'monthly' => null,
                ];
            }

            if ($grouped_rows[$row_key][$price_type] === null) {
                $grouped_rows[$row_key][$price_type] = $amount;
            }
        }

        $rows = array_values($grouped_rows);
        usort(
            $rows,
            static function (array $left, array $right): int {
                $left_key = isset($left['date_start']) ? (string) $left['date_start'] : '';
                $right_key = isset($right['date_start']) ? (string) $right['date_start'] : '';
                return strcmp($left_key, $right_key);
            }
        );

        return array_map(
            function (array $row) use ($currency): array {
                $daily = isset($row['daily']) && is_numeric($row['daily']) ? (float) $row['daily'] : null;
                $weekly = isset($row['weekly']) && is_numeric($row['weekly']) ? (float) $row['weekly'] : null;
                $monthly = isset($row['monthly']) && is_numeric($row['monthly']) ? (float) $row['monthly'] : null;
                $daily_display = $daily !== null ? $this->format_currency($daily, $currency) : 'N/A';
                $weekly_display = $weekly !== null ? $this->format_currency($weekly, $currency) : 'N/A';
                $monthly_display = $monthly !== null ? $this->format_currency($monthly, $currency) : 'N/A';

                return [
                    'date_start' => (string) ($row['date_start'] ?? ''),
                    'date_end' => (string) ($row['date_end'] ?? ''),
                    'date_range' => (string) ($row['date_range'] ?? ''),
                    'daily' => $daily,
                    'weekly' => $weekly,
                    'monthly' => $monthly,
                    'daily_display' => $daily_display,
                    'weekly_display' => $weekly_display,
                    'monthly_display' => $monthly_display,
                    'search_index' => strtolower(
                        trim(
                            implode(
                                ' ',
                                [
                                    (string) ($row['date_range'] ?? ''),
                                    $daily_display,
                                    $weekly_display,
                                    $monthly_display,
                                ]
                            )
                        )
                    ),
                ];
            },
            $rows
        );
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

    private function format_date_range(string $start, string $end): string
    {
        $start_formatted = $this->format_display_date($start);
        $end_formatted = $this->format_display_date($end);

        return $start_formatted . ' - ' . $end_formatted;
    }

    private function format_display_date(string $ymd): string
    {
        $timestamp = strtotime($ymd);
        if ($timestamp === false) {
            return $ymd;
        }

        return gmdate('m/d/Y', $timestamp);
    }

    private function format_currency(float $amount, string $currency): string
    {
        return $currency . number_format($amount, 2);
    }

    private function sanitize_currency(string $value): string
    {
        $currency = trim(sanitize_text_field($value));

        return $currency !== '' ? $currency : '$';
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

    private function normalize_boolean(mixed $value, bool $default = true): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        if (!is_scalar($value)) {
            return $default;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function sanitize_text(string $value, string $fallback): string
    {
        $normalized = trim(sanitize_text_field($value));

        return $normalized !== '' ? $normalized : $fallback;
    }
}
