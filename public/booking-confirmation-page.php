<?php

namespace BarefootEngine\Core;

use BarefootEngine\Properties\Property_Booking_Checkout_Service;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Booking_Confirmation_Page
{
    public const PAGE_QUERY_VAR = 'be_booking_confirmed';
    public const ICS_QUERY_VAR = 'be_booking_confirmed_ics';
    private const TEMPLATE_QUERY_VAR = 'barefoot_engine_booking_confirmation_context';
    private const PAGE_PATH = 'booking-confirmed';
    private const ICS_PATH = 'booking-confirmed/ics';

    private Property_Booking_Checkout_Service $checkout_service;

    public function __construct(?Property_Booking_Checkout_Service $checkout_service = null)
    {
        $this->checkout_service = $checkout_service ?? new Property_Booking_Checkout_Service();
    }

    public static function register_rewrite_rules_static(): void
    {
        add_rewrite_tag('%' . self::PAGE_QUERY_VAR . '%', '([^&]+)');
        add_rewrite_tag('%' . self::ICS_QUERY_VAR . '%', '([^&]+)');

        add_rewrite_rule(
            '^booking-confirmed/?$',
            'index.php?' . self::PAGE_QUERY_VAR . '=1',
            'top'
        );

        add_rewrite_rule(
            '^booking-confirmed/ics/?$',
            'index.php?' . self::ICS_QUERY_VAR . '=1',
            'top'
        );
    }

    public function register_rewrite_rules(): void
    {
        self::register_rewrite_rules_static();
    }

    /**
     * @param array<int, string> $vars
     * @return array<int, string>
     */
    public function register_query_vars(array $vars): array
    {
        $vars[] = self::PAGE_QUERY_VAR;
        $vars[] = self::ICS_QUERY_VAR;
        $vars[] = self::TEMPLATE_QUERY_VAR;

        return array_values(array_unique($vars));
    }

    public function maybe_handle_ics_download(): void
    {
        if (!$this->is_ics_request()) {
            return;
        }

        $token = $this->read_confirmation_token();
        $payload = $this->checkout_service->get_confirmation_ics_payload($token);

        if (is_wp_error($payload)) {
            $this->render_ics_error($payload);
        }

        nocache_headers();
        status_header(200);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name((string) ($payload['filename'] ?? 'booking.ics')) . '"');
        header('X-Robots-Tag: noindex, nofollow', true);

        echo (string) ($payload['content'] ?? '');
        exit;
    }

    public function maybe_use_confirmation_template(string $template): string
    {
        if (!$this->is_page_request()) {
            return $template;
        }

        $token = $this->read_confirmation_token();
        $context = $this->checkout_service->get_confirmation_page_context($token);

        if (is_wp_error($context)) {
            $status = $this->extract_error_status($context);
            status_header($status);
            $this->sync_query_state_for_template($status);
            set_query_var(
                self::TEMPLATE_QUERY_VAR,
                [
                    'valid' => false,
                    'title' => __('Booking confirmation unavailable', 'barefoot-engine'),
                    'message' => $context->get_error_message(),
                    'status' => $status,
                    'propertiesUrl' => home_url('/properties/'),
                ]
            );
        } else {
            status_header(200);
            $this->sync_query_state_for_template(200);
            set_query_var(self::TEMPLATE_QUERY_VAR, $context);
        }

        return BAREFOOT_ENGINE_PLUGIN_DIR . 'public/templates/booking-confirmed.php';
    }

    /**
     * @param array<string, string> $parts
     * @return array<string, string>
     */
    public function filter_document_title_parts(array $parts): array
    {
        if (!$this->is_page_request()) {
            return $parts;
        }

        $parts['title'] = __('Booking Confirmed', 'barefoot-engine');

        return $parts;
    }

    /**
     * @param array<int, string> $classes
     * @return array<int, string>
     */
    public function filter_body_class(array $classes): array
    {
        if (!$this->is_page_request()) {
            return $classes;
        }

        $classes[] = 'barefoot-engine-booking-confirmed-page';

        return $classes;
    }

    private function is_page_request(): bool
    {
        if ((string) get_query_var(self::PAGE_QUERY_VAR) !== '') {
            return true;
        }

        return $this->request_path_matches(self::PAGE_PATH);
    }

    private function is_ics_request(): bool
    {
        if ((string) get_query_var(self::ICS_QUERY_VAR) !== '') {
            return true;
        }

        return $this->request_path_matches(self::ICS_PATH);
    }

    private function read_confirmation_token(): string
    {
        if (!isset($_GET['confirmation'])) {
            return '';
        }

        $raw_token = wp_unslash($_GET['confirmation']);

        return is_scalar($raw_token) ? sanitize_text_field((string) $raw_token) : '';
    }

    private function extract_error_status(WP_Error $error): int
    {
        $data = $error->get_error_data();
        if (is_array($data) && isset($data['status']) && is_numeric($data['status'])) {
            return (int) $data['status'];
        }

        return 404;
    }

    private function render_ics_error(WP_Error $error): void
    {
        nocache_headers();
        status_header($this->extract_error_status($error));
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow', true);

        echo esc_html($error->get_error_message());
        exit;
    }

    private function request_path_matches(string $expected_path): bool
    {
        $request_path = $this->normalize_request_path();
        $normalized_expected_path = trim($expected_path, '/');

        return $request_path === $normalized_expected_path;
    }

    private function normalize_request_path(): string
    {
        $request_uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? wp_unslash($_SERVER['REQUEST_URI'])
            : '';
        $request_path = trim((string) wp_parse_url($request_uri, PHP_URL_PATH), '/');
        $home_path = trim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');

        if ($home_path !== '') {
            if ($request_path === $home_path) {
                $request_path = '';
            } elseif (str_starts_with($request_path, $home_path . '/')) {
                $request_path = substr($request_path, strlen($home_path) + 1);
            }
        }

        return trim($request_path, '/');
    }

    private function sync_query_state_for_template(int $status): void
    {
        global $post, $wp_query;

        if (!$wp_query instanceof \WP_Query) {
            return;
        }

        $is_error = $status >= 400;
        $virtual_post = $this->build_virtual_page_post();

        $wp_query->is_404 = $is_error;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->queried_object = $virtual_post;
        $wp_query->queried_object_id = (int) $virtual_post->ID;
        $wp_query->post = $virtual_post;
        $wp_query->posts = [$virtual_post];
        $wp_query->post_count = 1;
        $wp_query->found_posts = 1;
        $wp_query->max_num_pages = 1;
        $post = $virtual_post;

        setup_postdata($virtual_post);
    }

    private function build_virtual_page_post(): \WP_Post
    {
        return new \WP_Post(
            (object) [
                'ID' => 0,
                'post_author' => 0,
                'post_date' => current_time('mysql'),
                'post_date_gmt' => gmdate('Y-m-d H:i:s'),
                'post_content' => '',
                'post_title' => __('Booking Confirmed', 'barefoot-engine'),
                'post_excerpt' => '',
                'post_status' => 'publish',
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_password' => '',
                'post_name' => self::PAGE_PATH,
                'to_ping' => '',
                'pinged' => '',
                'post_modified' => current_time('mysql'),
                'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
                'post_content_filtered' => '',
                'post_parent' => 0,
                'guid' => home_url('/' . self::PAGE_PATH . '/'),
                'menu_order' => 0,
                'post_type' => 'page',
                'post_mime_type' => '',
                'comment_count' => 0,
                'filter' => 'raw',
            ]
        );
    }
}
