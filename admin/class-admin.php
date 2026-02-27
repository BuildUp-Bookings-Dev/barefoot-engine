<?php

namespace BarefootEngine\Admin;

use BarefootEngine\Includes\Helpers\Manifest;

if (!defined('ABSPATH')) {
    exit;
}

class Admin
{
    private Manifest $manifest;

    public function __construct()
    {
        $this->manifest = new Manifest();
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Barefoot Engine', 'barefoot-engine'),
            __('Barefoot Engine', 'barefoot-engine'),
            'manage_options',
            'barefoot-engine',
            [$this, 'render_page'],
            'dashicons-admin-home',
            56
        );
    }

    public function render_page(): void
    {
        $view = BAREFOOT_ENGINE_PLUGIN_DIR . 'admin/views/dashboard.php';
        if (file_exists($view)) {
            include $view;
        }
    }

    public function enqueue_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'toplevel_page_barefoot-engine') {
            return;
        }

        $script = $this->manifest->find_entry_by_name('admin-script');
        $style = $this->manifest->find_entry_by_source('assets/src/scss/admin/index.scss');

        if (is_array($script) && !empty($script['file'])) {
            wp_enqueue_script(
                'barefoot-engine-admin',
                BAREFOOT_ENGINE_PLUGIN_URL . 'assets/dist/' . ltrim((string) $script['file'], '/'),
                [],
                BAREFOOT_ENGINE_VERSION,
                true
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
    }
}
