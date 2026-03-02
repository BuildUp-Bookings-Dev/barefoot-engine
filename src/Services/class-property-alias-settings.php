<?php

namespace BarefootEngine\Services;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Alias_Settings
{
    public const OPTION_KEY = 'barefoot_engine_property_aliases';

    /**
     * @return array<string, string>
     */
    public function get_aliases(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            return [];
        }

        $aliases = [];

        foreach ($stored as $key => $label) {
            $sanitized_key = $this->sanitize_key_name($key);
            $sanitized_label = $this->sanitize_alias_label($label);
            if ($sanitized_key === '' || $sanitized_label === '') {
                continue;
            }

            $aliases[$sanitized_key] = $sanitized_label;
        }

        return $aliases;
    }

    /**
     * @param array<string, mixed> $aliases
     * @return array<string, string>
     */
    public function save_aliases(array $aliases): array
    {
        $normalized = [];

        foreach ($aliases as $key => $label) {
            $sanitized_key = $this->sanitize_key_name($key);
            $sanitized_label = $this->sanitize_alias_label($label);
            if ($sanitized_key === '' || $sanitized_label === '') {
                continue;
            }

            $normalized[$sanitized_key] = $sanitized_label;
        }

        update_option(self::OPTION_KEY, $normalized, false);

        return $normalized;
    }

    /**
     * @param array<string, string> $amenity_labels
     */
    public function get_default_label(string $key, array $amenity_labels = []): string
    {
        $sanitized_key = $this->sanitize_key_name($key);
        if ($sanitized_key === '') {
            return '';
        }

        if (isset($amenity_labels[$sanitized_key]) && is_string($amenity_labels[$sanitized_key])) {
            $amenity_label = trim($amenity_labels[$sanitized_key]);
            if ($amenity_label !== '') {
                return $amenity_label;
            }
        }

        return $this->humanize_key($sanitized_key);
    }

    /**
     * @param array<string, string> $amenity_labels
     */
    public function resolve_label(string $key, array $amenity_labels = []): string
    {
        $aliases = $this->get_aliases();
        $sanitized_key = $this->sanitize_key_name($key);
        if ($sanitized_key === '') {
            return '';
        }

        if (isset($aliases[$sanitized_key]) && $aliases[$sanitized_key] !== '') {
            return $aliases[$sanitized_key];
        }

        return $this->get_default_label($sanitized_key, $amenity_labels);
    }

    /**
     * @param array<int, string> $field_keys
     * @param array<string, string> $amenity_labels
     * @return array<int, string>
     */
    public function get_available_keys(array $field_keys, array $amenity_labels): array
    {
        $keys = [];

        foreach ($field_keys as $key) {
            $sanitized_key = $this->sanitize_key_name($key);
            if ($sanitized_key === '' || in_array($sanitized_key, $keys, true)) {
                continue;
            }

            $keys[] = $sanitized_key;
        }

        $amenity_keys = array_keys($amenity_labels);
        natcasesort($amenity_keys);

        foreach ($amenity_keys as $key) {
            $sanitized_key = $this->sanitize_key_name($key);
            if ($sanitized_key === '' || in_array($sanitized_key, $keys, true)) {
                continue;
            }

            $keys[] = $sanitized_key;
        }

        return array_values($keys);
    }

    /**
     * @param array<int, string> $field_keys
     * @param array<string, string> $amenity_labels
     * @return array<int, array<string, string>>
     */
    public function build_alias_rows(array $field_keys, array $amenity_labels): array
    {
        $aliases = $this->get_aliases();
        $rows = [];

        foreach ($this->get_available_keys($field_keys, $amenity_labels) as $key) {
            $default_label = $this->get_default_label($key, $amenity_labels);
            $alias = $aliases[$key] ?? '';

            $rows[] = [
                'key' => $key,
                'default_label' => $default_label,
                'alias' => $alias,
                'effective_label' => $alias !== '' ? $alias : $default_label,
            ];
        }

        return $rows;
    }

    private function humanize_key(string $key): string
    {
        if (preg_match('/^a\d+$/', $key) === 1) {
            return $key;
        }

        $lower = strtolower($key);
        $special_cases = [
            'propertyid' => 'Property ID',
            'keyboardid' => 'Keyboard ID',
            'addressid' => 'Address ID',
            'propaddressnew' => 'Prop Address New',
            'propaddress' => 'Prop Address',
            'mindays' => 'Min Days',
            'minprice' => 'Min Price',
            'maxprice' => 'Max Price',
            'imagepath' => 'Image Path',
            'extdescription' => 'Ext Description',
            'unitcomments' => 'Unit Comments',
            'internetdescription' => 'Internet Description',
            'videolink' => 'Video Link',
        ];

        if (isset($special_cases[$lower])) {
            return $special_cases[$lower];
        }

        $label = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        if (!is_string($label)) {
            return $key;
        }

        $label = str_replace(['_', '-'], ' ', $label);
        $label = preg_replace('/\s+/', ' ', trim($label));

        if (!is_string($label) || $label === '') {
            return $key;
        }

        return ucwords($label);
    }

    private function sanitize_key_name(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $string = wp_check_invalid_utf8((string) $value);
        $string = str_replace("\0", '', $string);
        $string = trim(wp_strip_all_tags($string));

        return preg_replace('/[\r\n]+/', '', $string) ?? '';
    }

    private function sanitize_alias_label(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return sanitize_text_field(trim((string) $value));
    }
}
