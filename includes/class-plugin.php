<?php

namespace BarefootEngine\Includes;

use BarefootEngine\Admin\Admin;
use BarefootEngine\PublicFacing\Public_Facing;
use BarefootEngine\Integrations\Github_Updater;
use BarefootEngine\REST\Api_Integration_Controller;
use BarefootEngine\Services\Api_Integration_Settings;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{
    private Loader $loader;

    public function __construct()
    {
        $this->loader = new Loader();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_rest_hooks();
        $this->define_integration_hooks();
    }

    private function define_admin_hooks(): void
    {
        $admin = new Admin();

        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_assets');
        $this->loader->add_action('admin_menu', $admin, 'register_menu');
    }

    private function define_public_hooks(): void
    {
        $public = new Public_Facing();

        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_assets');
    }

    private function define_rest_hooks(): void
    {
        $settings = new Api_Integration_Settings();
        $controller = new Api_Integration_Controller($settings);

        $this->loader->add_action('rest_api_init', $controller, 'register_routes', 10, 0);
    }

    private function define_integration_hooks(): void
    {
        $updater = new Github_Updater();

        $this->loader->add_action('plugins_loaded', $updater, 'register', 20);
    }

    public function run(): void
    {
        $this->loader->run();
    }
}
