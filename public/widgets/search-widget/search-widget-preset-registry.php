<?php

namespace BarefootEngine\Widgets\Search;

if (!defined('ABSPATH')) {
    exit;
}

class Search_Widget_Preset_Registry
{
    private const DEFAULT_PRESET_KEY = 'default';

    /**
     * @var array<string, mixed>
     */
    private const FALLBACK_PRESET = [
        'targetUrl' => '',
        'showLocation' => true,
        'filterDisplayMode' => 'left-slide',
        'showFilterButton' => false,
        'locationLabel' => 'Location',
        'locationPlaceholder' => 'Where are you going?',
        'dateLabel' => 'Dates',
        'datePlaceholder' => 'Check in — Check out',
        'fields' => [],
        'filters' => [],
        'calendarOptions' => [
            'monthsToShow' => 1,
            'datepickerPlacement' => 'auto',
            'defaultMinDays' => 1,
            'tooltipLabel' => 'Nights',
            'showTooltip' => true,
            'showClearButton' => true,
        ],
    ];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $resolved_presets = null;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function get_all(): array
    {
        if ($this->resolved_presets !== null) {
            return $this->resolved_presets;
        }

        $presets = $this->load_plugin_presets();
        $filtered_presets = apply_filters('barefoot_engine_search_widget_presets', $presets);

        if (!is_array($filtered_presets)) {
            $filtered_presets = $presets;
        }

        $resolved_presets = [];

        foreach ($filtered_presets as $preset_key => $preset) {
            $normalized_key = $this->sanitize_preset_key($preset_key);
            if ($normalized_key === '' || !is_array($preset)) {
                continue;
            }

            $resolved_presets[$normalized_key] = $this->merge_presets(
                self::FALLBACK_PRESET,
                $this->normalize_preset($preset)
            );
        }

        $this->resolved_presets = $resolved_presets;

        return $this->resolved_presets;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $widget_id): array
    {
        $presets = $this->get_all();
        $preset_key = $this->sanitize_preset_key($widget_id);

        if ($preset_key !== '' && isset($presets[$preset_key])) {
            return $presets[$preset_key];
        }

        if (isset($presets[self::DEFAULT_PRESET_KEY])) {
            return $presets[self::DEFAULT_PRESET_KEY];
        }

        return self::FALLBACK_PRESET;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function load_plugin_presets(): array
    {
        $preset_file = BAREFOOT_ENGINE_PLUGIN_DIR . 'config/search-widget/presets.php';
        if (!is_readable($preset_file)) {
            return [
                self::DEFAULT_PRESET_KEY => self::FALLBACK_PRESET,
            ];
        }

        $presets = require $preset_file;

        if (!is_array($presets)) {
            return [
                self::DEFAULT_PRESET_KEY => self::FALLBACK_PRESET,
            ];
        }

        return $presets;
    }

    private function sanitize_preset_key($preset_key): string
    {
        if (!is_string($preset_key)) {
            return '';
        }

        return sanitize_title_with_dashes(trim($preset_key));
    }

    /**
     * @param array<string, mixed> $preset
     * @return array<string, mixed>
     */
    private function normalize_preset(array $preset): array
    {
        $normalized = [];

        if (array_key_exists('targetUrl', $preset)) {
            $normalized['targetUrl'] = $this->sanitize_target_url((string) $preset['targetUrl']);
        }

        if (array_key_exists('showLocation', $preset)) {
            $normalized['showLocation'] = $this->normalize_boolean($preset['showLocation'], true);
        }

        if (array_key_exists('filterDisplayMode', $preset)) {
            $normalized['filterDisplayMode'] = $this->normalize_filter_display_mode($preset['filterDisplayMode']);
        }

        if (array_key_exists('showFilterButton', $preset)) {
            $normalized['showFilterButton'] = $this->normalize_boolean($preset['showFilterButton'], false);
        }

        if (array_key_exists('locationLabel', $preset)) {
            $normalized['locationLabel'] = $this->sanitize_text((string) $preset['locationLabel']);
        }

        if (array_key_exists('locationPlaceholder', $preset)) {
            $normalized['locationPlaceholder'] = $this->sanitize_text((string) $preset['locationPlaceholder']);
        }

        if (array_key_exists('dateLabel', $preset)) {
            $normalized['dateLabel'] = $this->sanitize_text((string) $preset['dateLabel']);
        }

        if (array_key_exists('datePlaceholder', $preset)) {
            $normalized['datePlaceholder'] = $this->sanitize_text((string) $preset['datePlaceholder']);
        }

        if (array_key_exists('fields', $preset)) {
            $normalized['fields'] = is_array($preset['fields']) ? array_values($preset['fields']) : [];
        }

        if (array_key_exists('filters', $preset)) {
            $normalized['filters'] = is_array($preset['filters']) ? array_values($preset['filters']) : [];
        }

        if (isset($preset['calendarOptions']) && is_array($preset['calendarOptions'])) {
            $calendar_options = [];

            if (array_key_exists('monthsToShow', $preset['calendarOptions'])) {
                $calendar_options['monthsToShow'] = $this->normalize_months_to_show($preset['calendarOptions']['monthsToShow']);
            }

            if (array_key_exists('datepickerPlacement', $preset['calendarOptions'])) {
                $calendar_options['datepickerPlacement'] = $this->sanitize_text((string) $preset['calendarOptions']['datepickerPlacement']);
            }

            if (array_key_exists('defaultMinDays', $preset['calendarOptions'])) {
                $calendar_options['defaultMinDays'] = $this->normalize_min_days($preset['calendarOptions']['defaultMinDays']);
            }

            if (array_key_exists('tooltipLabel', $preset['calendarOptions'])) {
                $calendar_options['tooltipLabel'] = $this->sanitize_text((string) $preset['calendarOptions']['tooltipLabel']);
            }

            if (array_key_exists('showTooltip', $preset['calendarOptions'])) {
                $calendar_options['showTooltip'] = $this->normalize_boolean($preset['calendarOptions']['showTooltip'], true);
            }

            if (array_key_exists('showClearButton', $preset['calendarOptions'])) {
                $calendar_options['showClearButton'] = $this->normalize_boolean($preset['calendarOptions']['showClearButton'], true);
            }

            if (!empty($calendar_options)) {
                $normalized['calendarOptions'] = $calendar_options;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function merge_presets(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && $this->is_associative_array($base[$key])
                && $this->is_associative_array($value)
            ) {
                /** @var array<string, mixed> $base_value */
                $base_value = $base[$key];
                /** @var array<string, mixed> $override_value */
                $override_value = $value;
                $base[$key] = $this->merge_presets($base_value, $override_value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
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

    /**
     * @param mixed $value
     */
    private function normalize_min_days($value): int
    {
        $days = is_numeric($value) ? (int) $value : 1;

        return max(1, $days);
    }

    /**
     * @param mixed $value
     */
    private function normalize_filter_display_mode($value): string
    {
        if (!is_scalar($value)) {
            return 'left-slide';
        }

        $mode = strtolower(trim((string) $value));

        return in_array($mode, ['modal', 'left-slide'], true) ? $mode : 'left-slide';
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

    /**
     * @param array<mixed> $array
     */
    private function is_associative_array(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
