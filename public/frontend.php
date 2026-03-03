<?php

namespace BarefootEngine\Core;

use BarefootEngine\Services\General_Settings;
use BarefootEngine\Widgets\Search\Search_Widget_Shortcode;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend
{
    private const PUBLIC_SCRIPT_HANDLE = 'barefoot-engine-public';
    private const PUBLIC_STYLE_HANDLE = 'barefoot-engine-public';
    private const SEARCH_WIDGET_SCRIPT_HANDLE = 'barefoot-engine-search-widget';
    private const SEARCH_WIDGET_STYLE_HANDLE = 'barefoot-engine-search-widget';

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
        $this->register_search_widget_assets();

        $this->enqueue_script_entry(self::PUBLIC_SCRIPT_HANDLE, 'public-script');
        $this->enqueue_style_entry(self::PUBLIC_STYLE_HANDLE, 'public-style');

        if (Search_Widget_Shortcode::should_enqueue_assets()) {
            wp_enqueue_script(self::SEARCH_WIDGET_SCRIPT_HANDLE);
            wp_enqueue_style(self::SEARCH_WIDGET_STYLE_HANDLE);
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

    private function register_search_widget_assets(): void
    {
        $script = $this->manifest->find_entry_by_name('search-widget-script');
        if (is_array($script) && !empty($script['file'])) {
            wp_register_script(
                self::SEARCH_WIDGET_SCRIPT_HANDLE,
                $this->build_asset_url((string) $script['file']),
                [],
                BAREFOOT_ENGINE_VERSION,
                true
            );
        }

        $style = $this->manifest->find_entry_by_name('search-widget-style');
        if (is_array($style) && !empty($style['file'])) {
            wp_register_style(
                self::SEARCH_WIDGET_STYLE_HANDLE,
                $this->build_asset_url((string) $style['file']),
                [],
                BAREFOOT_ENGINE_VERSION
            );
        }
    }

    private function enqueue_script_entry(string $handle, string $entry_name): void
    {
        $entry = $this->manifest->find_entry_by_name($entry_name);
        if (!is_array($entry) || empty($entry['file'])) {
            return;
        }

        wp_enqueue_script(
            $handle,
            $this->build_asset_url((string) $entry['file']),
            [],
            BAREFOOT_ENGINE_VERSION,
            true
        );
    }

    private function enqueue_style_entry(string $handle, string $entry_name): void
    {
        $entry = $this->manifest->find_entry_by_name($entry_name);
        if (!is_array($entry) || empty($entry['file'])) {
            return;
        }

        wp_enqueue_style(
            $handle,
            $this->build_asset_url((string) $entry['file']),
            [],
            BAREFOOT_ENGINE_VERSION
        );
    }

    private function build_asset_url(string $file): string
    {
        return BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim($file, '/');
    }
}
