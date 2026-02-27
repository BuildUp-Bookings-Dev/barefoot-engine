<?php

namespace BarefootEngine\PublicFacing;

use BarefootEngine\Includes\Helpers\Manifest;
use BarefootEngine\Services\General_Settings;

if (!defined('ABSPATH')) {
    exit;
}

class Public_Facing
{
    private Manifest $manifest;
    private General_Settings $general_settings;

    public function __construct()
    {
        $this->manifest = new Manifest();
        $this->general_settings = new General_Settings();
    }

    public function enqueue_assets(): void
    {
        $settings = $this->general_settings->get_settings();
        $this->enqueue_selected_fonts($settings);

        $script = $this->manifest->find_entry_by_name('public-script');
        $style = $this->manifest->find_entry_by_source('assets/src/scss/public/index.scss');

        if (is_array($script) && !empty($script['file'])) {
            wp_enqueue_script(
                'barefoot-engine-public',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $script['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION,
                true
            );
        }

        if (is_array($style) && !empty($style['file'])) {
            wp_enqueue_style(
                'barefoot-engine-public',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $style['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION
            );
        }
    }

    public function render_custom_css(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = $this->general_settings->get_settings();
        $computed_css = $this->build_computed_frontend_css($settings);
        $custom_css = isset($settings['custom_css']) && is_string($settings['custom_css']) ? trim($settings['custom_css']) : '';

        $css_content = trim($computed_css . PHP_EOL . $custom_css);
        if ($css_content === '') {
            return;
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS is normalized in settings service.
        echo '<style class="be-custom-css">' . PHP_EOL . $css_content . PHP_EOL . '</style>' . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function enqueue_selected_fonts(array $settings): void
    {
        $tokens = $this->general_settings->get_selected_google_font_tokens($settings);
        if (empty($tokens)) {
            return;
        }

        $families = array_map(
            static fn(string $token): string => rawurlencode($token),
            $tokens
        );

        $font_url = 'https://fonts.googleapis.com/css2?family=' . implode('&family=', $families) . '&display=swap';

        wp_enqueue_style(
            'barefoot-engine-public-fonts',
            $font_url,
            [],
            null
        );
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function build_computed_frontend_css(array $settings): string
    {
        $colors = isset($settings['colors']) && is_array($settings['colors']) ? $settings['colors'] : [];
        $typography = isset($settings['typography']) && is_array($settings['typography']) ? $settings['typography'] : [];

        $primary = isset($colors['primary']) && is_string($colors['primary']) ? $colors['primary'] : '#111111';
        $secondary = isset($colors['secondary']) && is_string($colors['secondary']) ? $colors['secondary'] : '#64748b';
        $accent = isset($colors['accent']) && is_string($colors['accent']) ? $colors['accent'] : '#3b82f6';

        $css = '.barefoot-engine-public{--be-color-primary:' . $primary . ';--be-color-secondary:' . $secondary . ';--be-color-accent:' . $accent . ';}' . PHP_EOL;

        $heading_declarations = [];
        $header_font_family = isset($typography['header_font_family']) && is_string($typography['header_font_family']) ? $typography['header_font_family'] : 'inherit';
        $header_font_size = isset($typography['header_font_size']) ? $typography['header_font_size'] : null;

        if ($header_font_family !== 'inherit') {
            $font_stack = $this->general_settings->get_font_stack($header_font_family);
            if (is_string($font_stack) && $font_stack !== '') {
                $heading_declarations[] = 'font-family:' . $font_stack;
            }
        }

        if (is_int($header_font_size)) {
            $heading_declarations[] = 'font-size:' . $header_font_size . 'px';
        }

        if (!empty($heading_declarations)) {
            $css .= '.barefoot-engine-public .be-heading{' . implode(';', $heading_declarations) . ';}' . PHP_EOL;
        }

        $label_declarations = [];
        $label_font_family = isset($typography['label_font_family']) && is_string($typography['label_font_family']) ? $typography['label_font_family'] : 'inherit';
        $label_font_size = isset($typography['label_font_size']) ? $typography['label_font_size'] : null;

        if ($label_font_family !== 'inherit') {
            $font_stack = $this->general_settings->get_font_stack($label_font_family);
            if (is_string($font_stack) && $font_stack !== '') {
                $label_declarations[] = 'font-family:' . $font_stack;
            }
        }

        if (is_int($label_font_size)) {
            $label_declarations[] = 'font-size:' . $label_font_size . 'px';
        }

        if (!empty($label_declarations)) {
            $css .= '.barefoot-engine-public .be-label,.barefoot-engine-public .be-label-text{' . implode(';', $label_declarations) . ';}' . PHP_EOL;
        }

        $body_declarations = [];
        $body_font_family = isset($typography['body_font_family']) && is_string($typography['body_font_family']) ? $typography['body_font_family'] : 'inherit';
        $body_font_size = isset($typography['body_font_size']) ? $typography['body_font_size'] : null;

        if ($body_font_family !== 'inherit') {
            $font_stack = $this->general_settings->get_font_stack($body_font_family);
            if (is_string($font_stack) && $font_stack !== '') {
                $body_declarations[] = 'font-family:' . $font_stack;
            }
        }

        if (is_int($body_font_size)) {
            $body_declarations[] = 'font-size:' . $body_font_size . 'px';
        }

        if (!empty($body_declarations)) {
            $css .= '.barefoot-engine-public .be-paragraph{' . implode(';', $body_declarations) . ';}' . PHP_EOL;
        }

        return trim($css);
    }
}
