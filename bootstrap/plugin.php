<?php

namespace BarefootEngine\Core;

use BarefootEngine\Admin\Admin;
use BarefootEngine\Integrations\Github_Updater;
use BarefootEngine\Properties\Property_Admin_Actions;
use BarefootEngine\Properties\Property_Availability_Service;
use BarefootEngine\Properties\Property_Listings_Provider;
use BarefootEngine\Properties\Property_Metaboxes;
use BarefootEngine\Properties\Property_Post_Type;
use BarefootEngine\Properties\Property_Taxonomies;
use BarefootEngine\REST\Api_Integration_Controller;
use BarefootEngine\REST\General_Settings_Controller;
use BarefootEngine\REST\Property_Availability_Controller;
use BarefootEngine\REST\Properties_Controller;
use BarefootEngine\REST\Updates_Controller;
use BarefootEngine\Services\Api_Integration_Settings;
use BarefootEngine\Services\Barefoot_Api_Client;
use BarefootEngine\Services\General_Settings;
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
    private ?Api_Integration_Settings $api_settings = null;
    private ?Barefoot_Api_Client $api_client = null;
    private ?Property_Taxonomies $property_taxonomies = null;
    private ?Property_Sync_Service $property_sync_service = null;
    private ?Property_Availability_Service $property_availability_service = null;

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
        $property_listings_provider = new Property_Listings_Provider($this->get_property_availability_service());
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
        $settings = $this->get_api_settings();
        $api_client = $this->get_api_client();
        $controller = new Api_Integration_Controller($settings, $api_client);
        $general_settings = new General_Settings();
        $general_controller = new General_Settings_Controller($general_settings);
        $updates_service = new Updates_Service();
        $updates_controller = new Updates_Controller($updates_service);
        $properties_controller = new Properties_Controller($this->get_property_sync_service());
        $availability_controller = new Property_Availability_Controller($this->get_property_availability_service());

        $this->loader->add_action('rest_api_init', $controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $general_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $updates_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $properties_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $availability_controller, 'register_routes', 10, 0);
    }

    private function define_property_hooks(): void
    {
        $post_type = new Property_Post_Type();
        $taxonomies = $this->get_property_taxonomies();
        $metaboxes = new Property_Metaboxes();
        $admin_actions = new Property_Admin_Actions($this->get_property_sync_service());

        $this->loader->add_action('init', $post_type, 'register', 10, 0);
        $this->loader->add_action('init', $taxonomies, 'register', 10, 0);
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

    private function get_api_settings(): Api_Integration_Settings
    {
        if (!$this->api_settings instanceof Api_Integration_Settings) {
            $this->api_settings = new Api_Integration_Settings();
        }

        return $this->api_settings;
    }

    private function get_api_client(): Barefoot_Api_Client
    {
        if (!$this->api_client instanceof Barefoot_Api_Client) {
            $this->api_client = new Barefoot_Api_Client();
        }

        return $this->api_client;
    }

    private function get_property_taxonomies(): Property_Taxonomies
    {
        if (!$this->property_taxonomies instanceof Property_Taxonomies) {
            $this->property_taxonomies = new Property_Taxonomies();
        }

        return $this->property_taxonomies;
    }

    private function get_property_sync_service(): Property_Sync_Service
    {
        if (!$this->property_sync_service instanceof Property_Sync_Service) {
            $this->property_sync_service = new Property_Sync_Service(
                $this->get_api_client(),
                $this->get_api_settings(),
                null,
                $this->get_property_taxonomies()
            );
        }

        return $this->property_sync_service;
    }

    private function get_property_availability_service(): Property_Availability_Service
    {
        if (!$this->property_availability_service instanceof Property_Availability_Service) {
            $this->property_availability_service = new Property_Availability_Service(
                $this->get_api_client(),
                $this->get_api_settings()
            );
        }

        return $this->property_availability_service;
    }
}
