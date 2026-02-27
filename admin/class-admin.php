<?php

namespace BarefootEngine\Admin;

use BarefootEngine\Includes\Helpers\Manifest;
use BarefootEngine\Services\Api_Integration_Settings;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    private const MENU_SLUG = 'barefoot-engine';

    private Manifest $manifest;
    private Api_Integration_Settings $api_integration_settings;

    public function __construct()
    {
        $this->manifest = new Manifest();
        $this->api_integration_settings = new Api_Integration_Settings();
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Barefoot Engine', 'barefoot-engine'),
            __('Barefoot Engine', 'barefoot-engine'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_page'],
            'dashicons-admin-home',
            56
        );
    }

    public function render_page(): void
    {
        $tabs = $this->get_tabs();
        $active_slug = $this->resolve_active_tab($tabs);
        $active_tab = $tabs[$active_slug] ?? $tabs['updates'];

        $components_dir = BAREFOOT_ENGINE_PLUGIN_DIR . 'admin/views/components/';
        $tabs_dir = BAREFOOT_ENGINE_PLUGIN_DIR . 'admin/views/tabs/';

        $active_template = $tabs_dir . (string) ($active_tab['template'] ?? 'updates.php');

        if (!file_exists($active_template)) {
            $active_template = $tabs_dir . 'updates.php';
        }

        $view = BAREFOOT_ENGINE_PLUGIN_DIR . 'admin/views/dashboard.php';
        if (file_exists($view)) {
            include $view;
        }
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'toplevel_page_' . self::MENU_SLUG) {
            return;
        }

        wp_enqueue_style(
            'barefoot-engine-font-inter',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'barefoot-engine-font-material-symbols',
            'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0',
            [],
            null
        );

        $script = $this->manifest->find_entry_by_name('admin-script');
        $style = $this->manifest->find_entry_by_source('assets/src/scss/admin/index.scss');
        $tailwind_style = $this->manifest->find_entry_by_source('assets/src/css/admin-tailwind.css');

        if (is_array($script) && !empty($script['file'])) {
            wp_enqueue_script(
                'barefoot-engine-admin',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $script['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION,
                true
            );

            $tabs = $this->get_tabs();
            $active_tab = $this->resolve_active_tab($tabs);

            wp_localize_script(
                'barefoot-engine-admin',
                'BarefootEngineAdmin',
                [
                    'restBase' => esc_url_raw(rest_url('barefoot-engine/v1/')),
                    'restNonce' => wp_create_nonce('wp_rest'),
                    'activeTab' => $active_tab,
                    'apiIntegration' => $this->api_integration_settings->get_public_settings(),
                ]
            );
        }

        if (is_array($style) && !empty($style['file'])) {
            wp_enqueue_style(
                'barefoot-engine-admin',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $style['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION
            );
        }

        if (is_array($tailwind_style) && !empty($tailwind_style['file'])) {
            wp_enqueue_style(
                'barefoot-engine-admin-tailwind',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $tailwind_style['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION
            );
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function get_tabs(): array
    {
        $tabs = [
            'general' => [
                'slug' => 'general',
                'label' => __('General', 'barefoot-engine'),
                'icon' => 'settings',
                'title' => __('General Settings', 'barefoot-engine'),
                'subtitle' => __('Configure general widget appearance and add your own custom styles.', 'barefoot-engine'),
                'template' => 'general.php',
            ],
            'api-integration' => [
                'slug' => 'api-integration',
                'label' => __('API Integration', 'barefoot-engine'),
                'icon' => 'code',
                'title' => __('API Integration', 'barefoot-engine'),
                'subtitle' => __('Configure your API credentials to synchronize data securely with your Barefoot API.', 'barefoot-engine'),
                'template' => 'api-integration.php',
            ],
            'updates' => [
                'slug' => 'updates',
                'label' => __('Updates', 'barefoot-engine'),
                'icon' => 'update',
                'title' => __('Updates', 'barefoot-engine'),
                'subtitle' => __('Stay up to date with the latest features and security patches.', 'barefoot-engine'),
                'template' => 'updates.php',
            ],
            'help' => [
                'slug' => 'help',
                'label' => __('Help', 'barefoot-engine'),
                'icon' => 'help',
                'title' => __('Help & Documentation', 'barefoot-engine'),
                'subtitle' => __('Reference for shortcodes, troubleshooting guides, and common integration patterns.', 'barefoot-engine'),
                'template' => 'help.php',
            ],
        ];

        foreach ($tabs as $key => $tab) {
            $tabs[$key]['url'] = $this->build_tab_url($tab['slug']);
        }

        return $tabs;
    }

    /**
     * @param array<string, array<string, string>> $tabs Tab config.
     */
    private function resolve_active_tab(array $tabs): string
    {
        $requested_tab = 'updates';

        if (isset($_GET['tab']) && is_string($_GET['tab'])) {
            $requested_tab = sanitize_key(wp_unslash($_GET['tab']));
        }

        if ($requested_tab !== '' && isset($tabs[$requested_tab])) {
            return $requested_tab;
        }

        return 'updates';
    }

    private function build_tab_url(string $tab_slug): string
    {
        return add_query_arg(
            [
                'page' => self::MENU_SLUG,
                'tab' => $tab_slug,
            ],
            admin_url('admin.php')
        );
    }
}
