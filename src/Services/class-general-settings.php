<?php

namespace BarefootEngine\Services;

if (!defined('ABSPATH')) {
    exit;
}

class General_Settings
{
    public const OPTION_KEY = 'barefoot_engine_general';
    public const FONT_SIZE_MIN = 12;
    public const FONT_SIZE_MAX = 72;
    public const FONT_SIZE_STEP = 1;
    private const TYPOGRAPHY_FAMILY_FIELDS = ['header_font_family', 'label_font_family', 'body_font_family'];
    private const TYPOGRAPHY_SIZE_FIELDS = ['header_font_size', 'label_font_size', 'body_font_size'];
    private const MAX_CUSTOM_CSS_LENGTH = 20000;

    /**
     * @return array<string, mixed>
     */
    public function get_settings(): array
    {
        $raw = get_option(self::OPTION_KEY, []);
        if (!is_array($raw)) {
            $raw = [];
        }

        $prepared = $this->prepare_payload($raw, false);

        return $prepared['settings'];
    }

    /**
     * @return array<string, mixed>
     */
    public function get_public_settings(): array
    {
        return $this->get_settings();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function get_font_options(): array
    {
        $options = [
            [
                'value' => 'inherit',
                'label' => 'Inherit',
            ],
        ];

        foreach ($this->get_font_kit() as $key => $font) {
            $options[] = [
                'value' => $key,
                'label' => $font['label'],
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $base_settings
     * @return array{settings: array<string, mixed>, errors: array<string, string>}
     */
    public function prepare_payload(array $payload, bool $strict = true, ?array $base_settings = null): array
    {
        $settings = is_array($base_settings) ? $base_settings : $this->get_defaults();
        $errors = [];

        if (array_key_exists('colors', $payload)) {
            if (!is_array($payload['colors'])) {
                if ($strict) {
                    $errors['colors'] = __('Invalid color settings payload.', 'barefoot-engine');
                }
            } else {
                $this->hydrate_colors($settings, $errors, $payload['colors'], $strict);
            }
        }

        if (array_key_exists('typography', $payload)) {
            if (!is_array($payload['typography'])) {
                if ($strict) {
                    $errors['typography'] = __('Invalid typography settings payload.', 'barefoot-engine');
                }
            } else {
                $this->hydrate_typography($settings, $errors, $payload['typography'], $strict);
            }
        }

        if (array_key_exists('custom_css', $payload)) {
            $custom_css = $this->normalize_custom_css($payload['custom_css']);
            if ($custom_css === null) {
                if ($strict) {
                    $errors['custom_css'] = __('Custom CSS is invalid or too long.', 'barefoot-engine');
                }
            } else {
                $settings['custom_css'] = $custom_css;
            }
        }

        return [
            'settings' => $settings,
            'errors' => $errors,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{settings: array<string, mixed>, errors: array<string, string>}
     */
    public function prepare_for_save(array $payload): array
    {
        $current = $this->get_settings();
        $prepared = $this->prepare_payload($payload, true, $current);

        return [
            'settings' => $prepared['settings'],
            'errors' => $prepared['errors'],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    public function persist(array $settings): array
    {
        update_option(self::OPTION_KEY, $settings, false);

        return $settings;
    }

    /**
     * @param array<string, mixed>|null $settings
     * @return array<int, string>
     */
    public function get_selected_google_font_tokens(?array $settings = null): array
    {
        $resolved = is_array($settings) ? $settings : $this->get_settings();
        $typography = isset($resolved['typography']) && is_array($resolved['typography']) ? $resolved['typography'] : [];

        $selected = [];
        foreach (self::TYPOGRAPHY_FAMILY_FIELDS as $field) {
            $value = $typography[$field] ?? 'inherit';
            if (!is_string($value) || $value === 'inherit') {
                continue;
            }

            $token = $this->get_google_font_token($value);
            if ($token !== null) {
                $selected[$token] = $token;
            }
        }

        return array_values($selected);
    }

    public function get_font_stack(string $font_key): ?string
    {
        $kit = $this->get_font_kit();
        if (!isset($kit[$font_key])) {
            return null;
        }

        return $kit[$font_key]['stack'];
    }

    public function get_google_font_token(string $font_key): ?string
    {
        $kit = $this->get_font_kit();
        if (!isset($kit[$font_key])) {
            return null;
        }

        return $kit[$font_key]['google'];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function get_font_kit(): array
    {
        return [
            'inter' => [
                'label' => 'Inter',
                'stack' => '"Inter", sans-serif',
                'google' => 'Inter:wght@400;500;600;700;800;900',
            ],
            'roboto' => [
                'label' => 'Roboto',
                'stack' => '"Roboto", sans-serif',
                'google' => 'Roboto:wght@400;500;700;900',
            ],
            'poppins' => [
                'label' => 'Poppins',
                'stack' => '"Poppins", sans-serif',
                'google' => 'Poppins:wght@400;500;600;700;800',
            ],
            'jost' => [
                'label' => 'Jost',
                'stack' => '"Jost", sans-serif',
                'google' => 'Jost:wght@400;500;600;700',
            ],
            'instrument-sans' => [
                'label' => 'Instrument Sans',
                'stack' => '"Instrument Sans", sans-serif',
                'google' => 'Instrument+Sans:wght@400;500;600;700',
            ],
            'montserrat' => [
                'label' => 'Montserrat',
                'stack' => '"Montserrat", sans-serif',
                'google' => 'Montserrat:wght@400;500;600;700;800',
            ],
            'lato' => [
                'label' => 'Lato',
                'stack' => '"Lato", sans-serif',
                'google' => 'Lato:wght@400;700;900',
            ],
            'open-sans' => [
                'label' => 'Open Sans',
                'stack' => '"Open Sans", sans-serif',
                'google' => 'Open+Sans:wght@400;500;600;700;800',
            ],
            'oswald' => [
                'label' => 'Oswald',
                'stack' => '"Oswald", sans-serif',
                'google' => 'Oswald:wght@400;500;600;700',
            ],
            'raleway' => [
                'label' => 'Raleway',
                'stack' => '"Raleway", sans-serif',
                'google' => 'Raleway:wght@400;500;600;700;800',
            ],
            'source-code-pro' => [
                'label' => 'Source Code Pro',
                'stack' => '"Source Code Pro", monospace',
                'google' => 'Source+Code+Pro:wght@400;500;600;700',
            ],
            'playfair-display' => [
                'label' => 'Playfair Display',
                'stack' => '"Playfair Display", serif',
                'google' => 'Playfair+Display:wght@400;500;600;700;800',
            ],
            'merriweather' => [
                'label' => 'Merriweather',
                'stack' => '"Merriweather", serif',
                'google' => 'Merriweather:wght@400;700;900',
            ],
            'denk-one' => [
                'label' => 'Denk One',
                'stack' => '"Denk One", cursive',
                'google' => 'Denk+One',
            ],
            'inconsolata' => [
                'label' => 'Inconsolata',
                'stack' => '"Inconsolata", monospace',
                'google' => 'Inconsolata:wght@400;500;600;700',
            ],
            'space-mono' => [
                'label' => 'Space Mono',
                'stack' => '"Space Mono", monospace',
                'google' => 'Space+Mono:wght@400;700',
            ],
            'playpen-sans' => [
                'label' => 'Playpen Sans',
                'stack' => '"Playpen Sans", cursive',
                'google' => 'Playpen+Sans:wght@400;500;600;700',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function get_defaults(): array
    {
        return [
            'colors' => [
                'primary' => '#111111',
                'secondary' => '#64748b',
                'accent' => '#3b82f6',
            ],
            'typography' => [
                'header_font_family' => 'inherit',
                'label_font_family' => 'inherit',
                'body_font_family' => 'inherit',
                'header_font_size' => null,
                'label_font_size' => null,
                'body_font_size' => null,
            ],
            'custom_css' => '',
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, string> $errors
     * @param array<string, mixed> $colors
     */
    private function hydrate_colors(array &$settings, array &$errors, array $colors, bool $strict): void
    {
        foreach (['primary', 'secondary', 'accent'] as $key) {
            if (!array_key_exists($key, $colors)) {
                continue;
            }

            $normalized = $this->normalize_hex($colors[$key]);
            if ($normalized === null) {
                if ($strict) {
                    $errors['colors.' . $key] = sprintf(
                        /* translators: %s is the color label. */
                        __('%s must be a valid hex color.', 'barefoot-engine'),
                        ucfirst(str_replace('_', ' ', $key))
                    );
                }
                continue;
            }

            $settings['colors'][$key] = $normalized;
        }
    }

    /**
     * @param array<string, mixed> $settings
     * @param array<string, string> $errors
     * @param array<string, mixed> $typography
     */
    private function hydrate_typography(array &$settings, array &$errors, array $typography, bool $strict): void
    {
        foreach (self::TYPOGRAPHY_FAMILY_FIELDS as $family_key) {
            if (!array_key_exists($family_key, $typography)) {
                continue;
            }

            $normalized_family = $this->normalize_font_key($typography[$family_key]);
            if ($normalized_family === null) {
                if ($strict) {
                    $errors['typography.' . $family_key] = __('Selected font is not supported.', 'barefoot-engine');
                }
                continue;
            }

            $settings['typography'][$family_key] = $normalized_family;
        }

        foreach (self::TYPOGRAPHY_SIZE_FIELDS as $size_key) {
            if (!array_key_exists($size_key, $typography)) {
                continue;
            }

            $normalized_size = $this->normalize_font_size($typography[$size_key]);
            if ($normalized_size === null && $typography[$size_key] !== null && $typography[$size_key] !== '' && $typography[$size_key] !== 'inherit') {
                if ($strict) {
                    $errors['typography.' . $size_key] = sprintf(
                        /* translators: 1: min font size, 2: max font size */
                        __('Font size must be inherit or a value between %1$d and %2$d.', 'barefoot-engine'),
                        self::FONT_SIZE_MIN,
                        self::FONT_SIZE_MAX
                    );
                }
                continue;
            }

            $settings['typography'][$size_key] = $normalized_size;
        }
    }

    private function normalize_hex(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $color = strtolower(trim((string) $value));
        if ($color === '') {
            return null;
        }

        if (preg_match('/^#([a-f0-9]{3})$/', $color, $matches) === 1) {
            $triplet = $matches[1];

            return sprintf('#%s%s%s%s%s%s', $triplet[0], $triplet[0], $triplet[1], $triplet[1], $triplet[2], $triplet[2]);
        }

        if (preg_match('/^#([a-f0-9]{6})$/', $color) === 1) {
            return $color;
        }

        return null;
    }

    private function normalize_font_key(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $font_key = sanitize_key((string) $value);
        if ($font_key === '' || $font_key === 'inherit') {
            return 'inherit';
        }

        $font_kit = $this->get_font_kit();
        if (!isset($font_kit[$font_key])) {
            return null;
        }

        return $font_key;
    }

    private function normalize_font_size(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 'inherit') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $size = (int) $value;
        if ($size < self::FONT_SIZE_MIN || $size > self::FONT_SIZE_MAX) {
            return null;
        }

        return $size;
    }

    private function normalize_custom_css(mixed $value): ?string
    {
        if ($value === null) {
            return '';
        }

        if (!is_scalar($value)) {
            return null;
        }

        $css = (string) $value;
        $css = str_replace("\0", '', $css);
        $css = preg_replace('#</?style[^>]*>#i', '', $css) ?? '';
        $css = str_replace('</style', '<\\/style', $css);

        $length = function_exists('mb_strlen') ? mb_strlen($css) : strlen($css);
        if ($length > self::MAX_CUSTOM_CSS_LENGTH) {
            return null;
        }

        return trim($css);
    }
}
