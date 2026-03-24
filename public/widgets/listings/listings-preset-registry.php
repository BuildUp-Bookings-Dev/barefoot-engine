<?php

namespace BarefootEngine\Widgets\Listings;

if (!defined('ABSPATH')) {
    exit;
}

class Listings_Preset_Registry
{
    private const DEFAULT_PRESET_KEY = 'default';

    /**
     * @var array<string, mixed>
     */
    private const FALLBACK_PRESET = [
        'listings' => [],
        'currency' => '$',
        'mapOptions' => [
            'center' => [14.55, 121.03],
            'zoom' => 12,
        ],
        'showMapToggle' => true,
        'showSort' => true,
        'showPagination' => true,
        'pageSize' => 12,
        'stickyMap' => true,
        'paginationMode' => 'infinite',
        'fullHeightMap' => false,
        'minDesktopColumns' => 3,
        'maxDesktopColumns' => 8,
        'markerFocusZoom' => 15,
        'markerFocusCenter' => null,
        'searchWidget' => [
            'targetUrl' => '',
            'showLocation' => true,
            'filterDisplayMode' => 'modal',
            'showFilterButton' => true,
            'locationLabel' => 'Keyword',
            'locationPlaceholder' => 'Search keyword',
            'dateLabel' => 'Dates',
            'datePlaceholder' => 'Check in — Check out',
            'fields' => [
                [
                    'label' => 'Guests',
                    'type' => 'select',
                    'options' => [
                        '1', '2', '3', '4', '5', '6', '7', '8', '9', '10',
                        '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
                        '21', '22', '23', '24', '25', '26', '27', '28', '29', '30',
                        '32', '34', '36', '40+',
                    ],
                    'position' => 'end',
                    'required' => false,
                    'key' => 'guests',
                    'icon' => 'fa-solid fa-users',
                ],
            ],
            'filters' => [
                [
                    'label' => 'Rating',
                    'type' => 'select',
                    'options' => [],
                    'required' => false,
                    'key' => 'rating',
                ],
                [
                    'label' => 'Type',
                    'type' => 'select',
                    'options' => [],
                    'required' => false,
                    'key' => 'type',
                ],
                [
                    'label' => 'Bedrooms',
                    'type' => 'select',
                    'options' => ['1', '2', '3', '4', '5', '6', '7', '8+'],
                    'required' => false,
                    'key' => 'bedrooms',
                ],
                [
                    'label' => 'Bathrooms',
                    'type' => 'select',
                    'options' => ['1', '2', '3', '4', '5', '6+'],
                    'required' => false,
                    'key' => 'bathrooms',
                ],
                [
                    'label' => 'Amenities',
                    'type' => 'checkbox',
                    'options' => [],
                    'required' => false,
                    'key' => 'amenities',
                ],
            ],
            'calendarOptions' => [
                'monthsToShow' => 2,
                'datepickerPlacement' => 'auto',
                'defaultMinDays' => 1,
                'tooltipLabel' => 'Nights',
                'showTooltip' => true,
                'showClearButton' => true,
            ],
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
        $filtered_presets = apply_filters('barefoot_engine_listings_presets', $presets);

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
        $preset_file = BAREFOOT_ENGINE_PLUGIN_DIR . 'config/listings/presets.php';
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

        if (array_key_exists('listings', $preset)) {
            $normalized['listings'] = is_array($preset['listings'])
                ? array_values(array_filter($preset['listings'], static fn($item): bool => is_array($item)))
                : [];
        }

        if (array_key_exists('currency', $preset)) {
            $currency = trim(sanitize_text_field((string) $preset['currency']));
            $normalized['currency'] = $currency !== '' ? $currency : '$';
        }

        if (isset($preset['mapOptions']) && is_array($preset['mapOptions'])) {
            $map_options = [];
            $center = $preset['mapOptions']['center'] ?? null;

            if (is_array($center) && count($center) >= 2) {
                $map_options['center'] = [
                    $this->normalize_coordinate($center[0], 14.55, -90, 90),
                    $this->normalize_coordinate($center[1], 121.03, -180, 180),
                ];
            }

            if (array_key_exists('zoom', $preset['mapOptions'])) {
                $map_options['zoom'] = $this->normalize_integer($preset['mapOptions']['zoom'], 12, 1, 20);
            }

            if (!empty($map_options)) {
                $normalized['mapOptions'] = $map_options;
            }
        }

        if (array_key_exists('showMapToggle', $preset)) {
            $normalized['showMapToggle'] = $this->normalize_boolean($preset['showMapToggle'], true);
        }

        if (array_key_exists('showSort', $preset)) {
            $normalized['showSort'] = $this->normalize_boolean($preset['showSort'], true);
        }

        if (array_key_exists('showPagination', $preset)) {
            $normalized['showPagination'] = $this->normalize_boolean($preset['showPagination'], true);
        }

        if (array_key_exists('pageSize', $preset)) {
            $normalized['pageSize'] = $this->normalize_page_size($preset['pageSize']);
        }

        if (array_key_exists('paginationMode', $preset)) {
            $normalized['paginationMode'] = $this->normalize_pagination_mode($preset['paginationMode']);
        }

        if (array_key_exists('stickyMap', $preset)) {
            $normalized['stickyMap'] = $this->normalize_boolean($preset['stickyMap'], false);
        }

        if (array_key_exists('fullHeightMap', $preset)) {
            $normalized['fullHeightMap'] = $this->normalize_boolean($preset['fullHeightMap'], true);
        }

        if (array_key_exists('minDesktopColumns', $preset)) {
            $normalized['minDesktopColumns'] = $this->normalize_desktop_column_count($preset['minDesktopColumns'], 3);
        }

        if (array_key_exists('maxDesktopColumns', $preset)) {
            $normalized['maxDesktopColumns'] = $this->normalize_desktop_column_count($preset['maxDesktopColumns'], 8);
        }

        if (array_key_exists('markerFocusZoom', $preset)) {
            $normalized['markerFocusZoom'] = $this->normalize_integer($preset['markerFocusZoom'], 15, 1, 19);
        }

        if (array_key_exists('markerFocusCenter', $preset)) {
            $normalized['markerFocusCenter'] = $this->normalize_marker_focus_center($preset['markerFocusCenter']);
        }

        if (isset($preset['searchWidget']) && is_array($preset['searchWidget'])) {
            $normalized['searchWidget'] = $this->normalize_search_widget_config($preset['searchWidget']);
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
    private function normalize_desktop_column_count($value, int $default): int
    {
        return $this->normalize_integer($value, $default, 1, 12);
    }

    /**
     * @param mixed $value
     */
    private function normalize_pagination_mode($value): string
    {
        if (!is_scalar($value)) {
            return 'pages';
        }

        $mode = strtolower(trim((string) $value));

        return in_array($mode, ['pages', 'infinite'], true) ? $mode : 'pages';
    }

    /**
     * @param mixed $value
     * @return array<int, float>|null
     */
    private function normalize_marker_focus_center($value): ?array
    {
        if (!is_array($value) || count($value) < 2) {
            return null;
        }

        return [
            $this->normalize_coordinate($value[0], 14.55, -90, 90),
            $this->normalize_coordinate($value[1], 121.03, -180, 180),
        ];
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

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function normalize_search_widget_config(array $config): array
    {
        $normalized = [];

        if (array_key_exists('targetUrl', $config)) {
            $target_url = trim(esc_url_raw((string) $config['targetUrl']));
            $normalized['targetUrl'] = $target_url;
        }

        if (array_key_exists('showLocation', $config)) {
            $normalized['showLocation'] = $this->normalize_boolean($config['showLocation'], true);
        }

        if (array_key_exists('filterDisplayMode', $config)) {
            $normalized['filterDisplayMode'] = $this->normalize_filter_display_mode($config['filterDisplayMode']);
        }

        if (array_key_exists('showFilterButton', $config)) {
            $normalized['showFilterButton'] = $this->normalize_boolean($config['showFilterButton'], false);
        }

        if (array_key_exists('locationLabel', $config)) {
            $normalized['locationLabel'] = sanitize_text_field((string) $config['locationLabel']);
        }

        if (array_key_exists('locationPlaceholder', $config)) {
            $normalized['locationPlaceholder'] = sanitize_text_field((string) $config['locationPlaceholder']);
        }

        if (array_key_exists('dateLabel', $config)) {
            $normalized['dateLabel'] = sanitize_text_field((string) $config['dateLabel']);
        }

        if (array_key_exists('datePlaceholder', $config)) {
            $normalized['datePlaceholder'] = sanitize_text_field((string) $config['datePlaceholder']);
        }

        if (array_key_exists('fields', $config)) {
            $normalized['fields'] = is_array($config['fields']) ? array_values($config['fields']) : [];
        }

        if (array_key_exists('filters', $config)) {
            $normalized['filters'] = is_array($config['filters']) ? array_values($config['filters']) : [];
        }

        if (isset($config['calendarOptions']) && is_array($config['calendarOptions'])) {
            $calendar_options = [];

            if (array_key_exists('monthsToShow', $config['calendarOptions'])) {
                $calendar_options['monthsToShow'] = max(1, min(6, (int) $config['calendarOptions']['monthsToShow']));
            }

            if (array_key_exists('datepickerPlacement', $config['calendarOptions'])) {
                $calendar_options['datepickerPlacement'] = sanitize_text_field((string) $config['calendarOptions']['datepickerPlacement']);
            }

            if (array_key_exists('defaultMinDays', $config['calendarOptions'])) {
                $calendar_options['defaultMinDays'] = max(1, (int) $config['calendarOptions']['defaultMinDays']);
            }

            if (array_key_exists('tooltipLabel', $config['calendarOptions'])) {
                $calendar_options['tooltipLabel'] = sanitize_text_field((string) $config['calendarOptions']['tooltipLabel']);
            }

            if (array_key_exists('showTooltip', $config['calendarOptions'])) {
                $calendar_options['showTooltip'] = $this->normalize_boolean($config['calendarOptions']['showTooltip'], true);
            }

            if (array_key_exists('showClearButton', $config['calendarOptions'])) {
                $calendar_options['showClearButton'] = $this->normalize_boolean($config['calendarOptions']['showClearButton'], true);
            }

            if (!empty($calendar_options)) {
                $normalized['calendarOptions'] = $calendar_options;
            }
        }

        return $normalized;
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
