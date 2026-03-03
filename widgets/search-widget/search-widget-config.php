<?php

namespace BarefootEngine\Widgets\Search;

if (!defined('ABSPATH')) {
    exit;
}

class Search_Widget_Config
{
    private const DEFAULTS = [
        'target_url' => '',
        'show_location' => 'true',
        'show_filter_button' => 'true',
        'location_label' => 'Location',
        'location_placeholder' => 'Where are you going?',
        'date_label' => 'Dates',
        'date_placeholder' => 'Check in - Check out',
        'fields' => '[]',
        'filters' => '[]',
        'months_to_show' => '2',
        'datepicker_placement' => 'auto',
        'class' => '',
    ];

    /**
     * @return array<string, string>
     */
    public function get_defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array{wrapper_class: string, widget_config: array<string, mixed>}
     */
    public function prepare_for_render(array $attributes): array
    {
        $target_url = $this->normalize_target_url($attributes['target_url'] ?? '');
        $months_to_show = $this->normalize_months_to_show($attributes['months_to_show'] ?? self::DEFAULTS['months_to_show']);

        return [
            'wrapper_class' => $this->normalize_css_classes($attributes['class'] ?? ''),
            'widget_config' => [
                'targetUrl' => $target_url,
                'showLocation' => $this->normalize_boolean($attributes['show_location'] ?? self::DEFAULTS['show_location'], true),
                'showFilterButton' => $this->normalize_boolean($attributes['show_filter_button'] ?? self::DEFAULTS['show_filter_button'], true),
                'locationLabel' => $this->sanitize_text($attributes['location_label'] ?? self::DEFAULTS['location_label']),
                'locationPlaceholder' => $this->sanitize_text($attributes['location_placeholder'] ?? self::DEFAULTS['location_placeholder']),
                'dateLabel' => $this->sanitize_text($attributes['date_label'] ?? self::DEFAULTS['date_label']),
                'datePlaceholder' => $this->sanitize_text($attributes['date_placeholder'] ?? self::DEFAULTS['date_placeholder']),
                'fields' => $this->decode_json_list($attributes['fields'] ?? self::DEFAULTS['fields']),
                'filters' => $this->decode_json_list($attributes['filters'] ?? self::DEFAULTS['filters']),
                'calendarOptions' => [
                    'monthsToShow' => $months_to_show,
                    'datepickerPlacement' => $this->sanitize_text($attributes['datepicker_placement'] ?? self::DEFAULTS['datepicker_placement']),
                ],
            ],
        ];
    }

    private function normalize_target_url(mixed $value): string
    {
        if (!is_scalar($value)) {
            return $this->get_current_url();
        }

        $target_url = trim((string) $value);
        if ($target_url === '') {
            return $this->get_current_url();
        }

        $sanitized = esc_url_raw($target_url);

        return $sanitized !== '' ? $sanitized : $this->get_current_url();
    }

    private function get_current_url(): string
    {
        $request_uri = '/';
        if (isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])) {
            $request_uri = wp_unslash($_SERVER['REQUEST_URI']);
        }

        $path = strtok($request_uri, '?');
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        return esc_url_raw(home_url($path));
    }

    private function normalize_boolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (!is_scalar($value)) {
            return $default;
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

    private function normalize_months_to_show(mixed $value): int
    {
        $normalized = is_scalar($value) ? (int) $value : 2;

        return max(1, min(6, $normalized));
    }

    /**
     * @return array<int, mixed>
     */
    private function decode_json_list(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function sanitize_text(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field((string) $value);
    }

    private function normalize_css_classes(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $tokens = preg_split('/\s+/', trim((string) $value));
        if (!is_array($tokens)) {
            return '';
        }

        $classes = [];
        foreach ($tokens as $token) {
            if (!is_string($token) || $token === '') {
                continue;
            }

            $sanitized = sanitize_html_class($token);
            if ($sanitized === '') {
                continue;
            }

            $classes[$sanitized] = $sanitized;
        }

        return implode(' ', $classes);
    }
}
