<?php

namespace BarefootEngine\Core;

use BarefootEngine\Properties\Property_Post_Type;
use BarefootEngine\Services\General_Settings;

if (!defined('ABSPATH')) {
    exit;
}

class Frontend
{
    private const PUBLIC_SCRIPT_HANDLE = 'barefoot-engine-public';
    private const PUBLIC_STYLE_HANDLE = 'barefoot-engine-public';
    private const FONT_AWESOME_HANDLE = 'barefoot-engine-font-awesome';
    private const FONT_AWESOME_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css';
    /**
     * @var array<int, string>
     */
    private const FONT_AWESOME_KNOWN_HANDLES = [
        'font-awesome',
        'font-awesome-5',
        'font-awesome-6',
        'font-awesome-7',
        'fontawesome',
        'fontawesome-free',
    ];
    /**
     * @var array<int, string>
     */
    private const MODULE_SCRIPT_HANDLES = [
        self::PUBLIC_SCRIPT_HANDLE,
    ];

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
        $this->maybe_enqueue_font_awesome();

        $this->enqueue_script_entry(self::PUBLIC_SCRIPT_HANDLE, 'public-script');
        $this->enqueue_style_entry(self::PUBLIC_STYLE_HANDLE, 'public-style');

        if (wp_script_is(self::PUBLIC_SCRIPT_HANDLE, 'enqueued')) {
            wp_localize_script(
                self::PUBLIC_SCRIPT_HANDLE,
                'BarefootEnginePublic',
                [
                    'restBase' => esc_url_raw(rest_url('barefoot-engine/v1/')),
                    'availabilitySearchEndpoint' => 'availability/search',
                    'availabilityPreflightEndpoint' => 'availability/preflight',
                    'bookingCalendarEndpoint' => 'booking/calendar',
                    'bookingQuoteEndpoint' => 'booking/quote',
                    'bookingCheckoutStartEndpoint' => 'booking-checkout/start',
                    'bookingCheckoutSessionEndpoint' => 'booking-checkout/session',
                    'bookingCheckoutCompleteEndpoint' => 'booking-checkout/complete',
                    'tracking' => $this->build_tracking_config($settings),
                ]
            );
        }
    }

    public function mark_module_scripts(string $tag, string $handle, string $src): string
    {
        if (!in_array($handle, self::MODULE_SCRIPT_HANDLES, true)) {
            return $tag;
        }

        return sprintf(
            '<script type="module" src="%s" id="%s-js"></script>' . PHP_EOL,
            esc_url($src),
            esc_attr($handle)
        );
    }

    public function render_tracking_head(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = $this->general_settings->get_settings();
        $tracking = $this->get_tracking_settings($settings);
        if (empty($tracking['enabled'])) {
            return;
        }

        $google_tag_id = (string) ($tracking['google_tag_id'] ?? '');
        echo '<script class="be-tracking-init">window.dataLayer=window.dataLayer||[];</script>' . PHP_EOL;

        if ($google_tag_id === '') {
            return;
        }

        if (str_starts_with($google_tag_id, 'GTM-')) {
            ?>
            <script class="be-google-tag-manager" data-be-google-tag-id="<?php echo esc_attr($google_tag_id); ?>">
                (function(w,d,s,l,i){w[l]=Array.isArray(w[l])?w[l]:[];var id='id='+encodeURIComponent(i);var hasExisting=Array.prototype.some.call(d.scripts,function(script){var src=script.src||'';return src.indexOf('googletagmanager.com/gtm.js')!==-1&&src.indexOf(id)!==-1;});if(hasExisting){return;}w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!=='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+encodeURIComponent(i)+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',<?php echo wp_json_encode($google_tag_id); ?>);
            </script>
            <?php
            return;
        }

        ?>
        <script class="be-google-tag-config" data-be-google-tag-id="<?php echo esc_attr($google_tag_id); ?>">
            (function(w,d,i){w.dataLayer=Array.isArray(w.dataLayer)?w.dataLayer:[];if(typeof w.gtag!=='function'){w.gtag=function(){w.dataLayer.push(arguments);};}var id='id='+encodeURIComponent(i);var hasExisting=Array.prototype.some.call(d.scripts,function(script){var src=script.src||'';return src.indexOf('googletagmanager.com/gtag/js')!==-1&&src.indexOf(id)!==-1;});if(!hasExisting){var first=d.getElementsByTagName('script')[0],tag=d.createElement('script');tag.async=true;tag.className='be-google-tag';tag.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(i);first.parentNode.insertBefore(tag,first);}var hasJs=w.dataLayer.some(function(entry){return entry&&entry[0]==='js';});if(!hasJs){w.gtag('js',new Date());}var hasConfig=w.dataLayer.some(function(entry){return entry&&entry[0]==='config'&&entry[1]===i;});if(!hasConfig){w.gtag('config',i,{'send_page_view':false});}})(window,document,<?php echo wp_json_encode($google_tag_id); ?>);
        </script>
        <?php
    }

    public function render_tracking_body(): void
    {
        if (is_admin()) {
            return;
        }

        $settings = $this->general_settings->get_settings();
        $tracking = $this->get_tracking_settings($settings);
        $google_tag_id = (string) ($tracking['google_tag_id'] ?? '');

        if (empty($tracking['enabled']) || !str_starts_with($google_tag_id, 'GTM-')) {
            return;
        }

        $iframe_src = add_query_arg(['id' => $google_tag_id], 'https://www.googletagmanager.com/ns.html');
        ?>
        <noscript class="be-google-tag-manager-noscript">
            <iframe src="<?php echo esc_url($iframe_src); ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe>
        </noscript>
        <?php
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
     * Enqueues Font Awesome from CDN unless another plugin or theme
     * has already enqueued it under a known handle.
     *
     * Only checks 'enqueued' (not 'registered') because some plugins
     * register FA handles without ever loading them, which would cause
     * a false positive and skip our enqueue entirely.
     */
    private function maybe_enqueue_font_awesome(): void
    {
        foreach (self::FONT_AWESOME_KNOWN_HANDLES as $handle) {
            if (wp_style_is($handle, 'enqueued')) {
                return;
            }
        }

        wp_enqueue_style(
            self::FONT_AWESOME_HANDLE,
            self::FONT_AWESOME_CDN,
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

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function build_tracking_config(array $settings): array
    {
        $tracking = $this->get_tracking_settings($settings);
        $config = [
            'enabled' => (bool) ($tracking['enabled'] ?? false),
            'googleTagId' => (string) ($tracking['google_tag_id'] ?? ''),
            'currency' => 'USD',
            'propertyView' => null,
        ];

        if (!$config['enabled'] || !is_singular(Property_Post_Type::POST_TYPE)) {
            return $config;
        }

        $post_id = get_queried_object_id();
        if (!is_numeric($post_id) || (int) $post_id <= 0) {
            return $config;
        }

        $property_id = get_post_meta((int) $post_id, '_be_property_id', true);
        $normalized_property_id = is_scalar($property_id) ? trim(sanitize_text_field((string) $property_id)) : '';
        if ($normalized_property_id === '') {
            $normalized_property_id = (string) (int) $post_id;
        }

        $title = get_the_title((int) $post_id);
        $normalized_title = is_string($title) && trim($title) !== ''
            ? trim(wp_strip_all_tags($title))
            : __('Property', 'barefoot-engine');

        $daily_rate = $this->resolve_property_daily_rate((int) $post_id);
        $tracking_price = $daily_rate !== null ? $daily_rate : 0.0;

        $config['propertyView'] = [
            'propertyId' => $normalized_property_id,
            'propertySummary' => [
                'propertyId' => $normalized_property_id,
                'title' => $normalized_title,
            ],
            'currency' => 'USD',
            'value' => $tracking_price,
            'price' => $tracking_price,
        ];

        return $config;
    }

    private function resolve_property_daily_rate(int $post_id): ?float
    {
        $rates = get_post_meta($post_id, '_be_property_rates', true);
        if (!is_array($rates) || $rates === []) {
            return null;
        }

        $today = wp_date('Y-m-d');
        $daily_rate = $this->find_property_rate_for_date($rates, $today) ?? $this->find_property_starting_rate($rates);
        if ($daily_rate === null) {
            return null;
        }

        $amount = $this->normalize_tracking_money($daily_rate['amount'] ?? $daily_rate['rent'] ?? null);

        return $amount !== null && $amount > 0 ? $amount : null;
    }

    /**
     * @param array<string, mixed> $rates
     * @return array<string, mixed>|null
     */
    private function find_property_rate_for_date(array $rates, string $target_date): ?array
    {
        $daily_rates = isset($rates['by_type']['daily']) && is_array($rates['by_type']['daily'])
            ? $rates['by_type']['daily']
            : [];

        foreach ($daily_rates as $rate) {
            if (is_array($rate) && $this->matches_property_rate_window($rate, $target_date)) {
                return $rate;
            }
        }

        $weekend_rates = isset($rates['by_type']['weekendany']) && is_array($rates['by_type']['weekendany'])
            ? $rates['by_type']['weekendany']
            : [];

        foreach ($weekend_rates as $rate) {
            if (
                is_array($rate)
                && $this->matches_property_rate_window($rate, $target_date)
                && $this->matches_property_weekend_range($rate, $target_date)
            ) {
                return $rate;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $rates
     * @return array<string, mixed>|null
     */
    private function find_property_starting_rate(array $rates): ?array
    {
        $candidates = [];

        foreach (['daily', 'weekendany'] as $rate_type) {
            $typed_rates = isset($rates['by_type'][$rate_type]) && is_array($rates['by_type'][$rate_type])
                ? $rates['by_type'][$rate_type]
                : [];

            foreach ($typed_rates as $rate) {
                if (!is_array($rate)) {
                    continue;
                }

                $amount = $this->normalize_tracking_money($rate['amount'] ?? $rate['rent'] ?? null);
                if ($amount === null || $amount <= 0) {
                    continue;
                }

                $rate['amount'] = $amount;
                $candidates[] = $rate;
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            static function (array $left, array $right): int {
                return ((float) ($left['amount'] ?? 0)) <=> ((float) ($right['amount'] ?? 0));
            }
        );

        return $candidates[0] ?? null;
    }

    private function normalize_tracking_money(mixed $value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function matches_property_rate_window(array $rate, string $target_date): bool
    {
        $start = isset($rate['date_start']) && is_string($rate['date_start']) ? trim($rate['date_start']) : '';
        $end = isset($rate['date_end']) && is_string($rate['date_end']) ? trim($rate['date_end']) : '';

        if (!$this->is_valid_tracking_date($start) || !$this->is_valid_tracking_date($end)) {
            return false;
        }

        return $target_date >= $start && $target_date <= $end;
    }

    /**
     * @param array<string, mixed> $rate
     */
    private function matches_property_weekend_range(array $rate, string $target_date): bool
    {
        $week_start = isset($rate['wk_b']) && is_scalar($rate['wk_b']) ? $this->normalize_tracking_weekday((string) $rate['wk_b']) : null;
        $week_end = isset($rate['wk_e']) && is_scalar($rate['wk_e']) ? $this->normalize_tracking_weekday((string) $rate['wk_e']) : null;

        if ($week_start === null || $week_end === null) {
            return false;
        }

        $target_weekday = (int) wp_date('w', strtotime($target_date . ' 00:00:00'));

        if ($week_start <= $week_end) {
            return $target_weekday >= $week_start && $target_weekday <= $week_end;
        }

        return $target_weekday >= $week_start || $target_weekday <= $week_end;
    }

    private function normalize_tracking_weekday(string $value): ?int
    {
        $normalized = strtolower(substr(trim($value), 0, 3));
        if ($normalized === '') {
            return null;
        }

        $map = [
            'sun' => 0,
            'mon' => 1,
            'tue' => 2,
            'wed' => 3,
            'thu' => 4,
            'fri' => 5,
            'sat' => 6,
        ];

        return $map[$normalized] ?? null;
    }

    private function is_valid_tracking_date(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    /**
     * @param array<string, mixed> $settings
     * @return array{enabled: bool, google_tag_id: string}
     */
    private function get_tracking_settings(array $settings): array
    {
        $tracking = isset($settings['tracking']) && is_array($settings['tracking']) ? $settings['tracking'] : [];

        return [
            'enabled' => !empty($tracking['enabled']),
            'google_tag_id' => isset($tracking['google_tag_id']) && is_string($tracking['google_tag_id'])
                ? $tracking['google_tag_id']
                : '',
        ];
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

        $this->enqueue_script_entry_styles($handle, $entry);
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

    /**
     * @param array<string, mixed> $entry
     */
    private function enqueue_script_entry_styles(string $handle, array $entry): void
    {
        $css_files = isset($entry['css']) && is_array($entry['css']) ? $entry['css'] : [];

        foreach ($css_files as $index => $file) {
            if (!is_string($file) || $file === '') {
                continue;
            }

            wp_enqueue_style(
                sprintf('%s-script-style-%d', $handle, $index),
                $this->build_asset_url($file),
                [],
                BAREFOOT_ENGINE_VERSION
            );
        }
    }
}
