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
