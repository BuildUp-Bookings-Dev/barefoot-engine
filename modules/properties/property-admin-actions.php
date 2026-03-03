<?php

namespace BarefootEngine\Properties;

use BarefootEngine\Services\Property_Sync_Service;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Property_Admin_Actions
{
    private Property_Sync_Service $sync_service;

    public function __construct(?Property_Sync_Service $sync_service = null)
    {
        $this->sync_service = $sync_service ?? new Property_Sync_Service();
    }

    public function sync_single_property(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to sync Barefoot properties.', 'barefoot-engine'), 403);
        }

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        check_admin_referer('be_sync_single_property_' . $post_id);

        $redirect_url = $post_id > 0
            ? get_edit_post_link($post_id, 'url')
            : admin_url('admin.php?page=barefoot-engine&tab=properties');

        if (!is_string($redirect_url) || $redirect_url === '') {
            $redirect_url = admin_url('admin.php?page=barefoot-engine&tab=properties');
        }

        $result = $this->sync_service->sync_single_post($post_id);
        if (is_wp_error($result)) {
            $this->redirect_with_result($redirect_url, false, $result->get_error_message());
        }

        $message = isset($result['message']) && is_string($result['message']) ? $result['message'] : __('Property sync completed successfully.', 'barefoot-engine');
        $this->redirect_with_result($redirect_url, true, $message);
    }

    public function render_admin_notice(): void
    {
        if (!is_admin()) {
            return;
        }

        if (!isset($_GET['be_property_sync_notice'], $_GET['be_property_sync_message'])) {
            return;
        }

        $notice = sanitize_key(wp_unslash($_GET['be_property_sync_notice']));
        $message = sanitize_text_field(wp_unslash($_GET['be_property_sync_message']));
        if ($message === '') {
            return;
        }

        $class = $notice === 'success' ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';

        printf(
            '<div class="%1$s"><p>%2$s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
    }

    private function redirect_with_result(string $redirect_url, bool $success, string $message): void
    {
        $url = add_query_arg(
            [
                'be_property_sync_notice' => $success ? 'success' : 'error',
                'be_property_sync_message' => $message,
            ],
            $redirect_url
        );

        wp_safe_redirect($url);
        exit;
    }
}
