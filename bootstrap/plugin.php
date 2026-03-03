<?php

namespace BarefootEngine\Core;

use BarefootEngine\Admin\Admin;
use BarefootEngine\Integrations\Github_Updater;
use BarefootEngine\Properties\Property_Admin_Actions;
use BarefootEngine\Properties\Property_Listings_Provider;
use BarefootEngine\Properties\Property_Metaboxes;
use BarefootEngine\Properties\Property_Post_Type;
use BarefootEngine\REST\Api_Integration_Controller;
use BarefootEngine\REST\General_Settings_Controller;
use BarefootEngine\REST\Properties_Controller;
use BarefootEngine\REST\Updates_Controller;
use BarefootEngine\Services\Api_Integration_Settings;
use BarefootEngine\Services\Barefoot_Api_Client;
use BarefootEngine\Services\General_Settings;
use BarefootEngine\Services\Property_Alias_Settings;
use BarefootEngine\Services\Property_Sync_Service;
use BarefootEngine\Services\Updates_Service;
use BarefootEngine\Widgets\Listings\Listings_Preset_Registry;
use BarefootEngine\Widgets\Listings\Listings_Shortcode;
use BarefootEngine\Widgets\Search\Search_Widget_Preset_Registry;
use BarefootEngine\Widgets\Search\Search_Widget_Shortcode;

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
        $this->define_property_hooks();
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
        $public = new Frontend();
        $listings_preset_registry = new Listings_Preset_Registry();
        $search_preset_registry = new Search_Widget_Preset_Registry();
        $property_listings_provider = new Property_Listings_Provider();
        $listings_shortcode = new Listings_Shortcode(
            $listings_preset_registry,
            $property_listings_provider,
            $search_preset_registry
        );
        $shortcode = new Search_Widget_Shortcode($search_preset_registry);

        $this->loader->add_action('init', $listings_shortcode, 'register', 10, 0);
        $this->loader->add_action('init', $shortcode, 'register', 10, 0);
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_assets');
        $this->loader->add_action('wp_head', $public, 'render_custom_css', 20, 0);
        $this->loader->add_filter('script_loader_tag', $public, 'mark_module_scripts', 10, 3);
    }

    private function define_rest_hooks(): void
    {
        $settings = new Api_Integration_Settings();
        $api_client = new Barefoot_Api_Client();
        $controller = new Api_Integration_Controller($settings, $api_client);
        $general_settings = new General_Settings();
        $general_controller = new General_Settings_Controller($general_settings);
        $updates_service = new Updates_Service();
        $updates_controller = new Updates_Controller($updates_service);
        $property_aliases = new Property_Alias_Settings();
        $property_sync = new Property_Sync_Service($api_client, $settings);
        $properties_controller = new Properties_Controller($property_aliases, $property_sync);

        $this->loader->add_action('rest_api_init', $controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $general_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $updates_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $properties_controller, 'register_routes', 10, 0);
    }

    private function define_property_hooks(): void
    {
        $property_aliases = new Property_Alias_Settings();
        $settings = new Api_Integration_Settings();
        $api_client = new Barefoot_Api_Client();
        $property_sync = new Property_Sync_Service($api_client, $settings);
        $post_type = new Property_Post_Type();
        $metaboxes = new Property_Metaboxes($property_aliases);
        $admin_actions = new Property_Admin_Actions($property_sync);

        $this->loader->add_action('init', $post_type, 'register', 10, 0);
        $this->loader->add_action('add_meta_boxes_' . Property_Post_Type::POST_TYPE, $metaboxes, 'register', 10, 0);
        $this->loader->add_action('admin_post_barefoot_engine_sync_property', $admin_actions, 'sync_single_property', 10, 0);
        $this->loader->add_action('admin_notices', $admin_actions, 'render_admin_notice', 10, 0);
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
