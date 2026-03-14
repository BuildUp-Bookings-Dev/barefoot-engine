<?php

namespace BarefootEngine\Core;

use BarefootEngine\Admin\Admin;
use BarefootEngine\Integrations\Github_Updater;
use BarefootEngine\Properties\Property_Admin_Actions;
use BarefootEngine\Properties\Property_Availability_Service;
use BarefootEngine\Properties\Property_Booking_Checkout_Service;
use BarefootEngine\Properties\Property_Booking_Records;
use BarefootEngine\Properties\Property_Delta_Refresh_Service;
use BarefootEngine\Properties\Property_Listings_Provider;
use BarefootEngine\Properties\Property_Metaboxes;
use BarefootEngine\Properties\Property_Post_Type;
use BarefootEngine\Properties\Property_Taxonomies;
use BarefootEngine\REST\Api_Integration_Controller;
use BarefootEngine\REST\General_Settings_Controller;
use BarefootEngine\REST\Property_Availability_Controller;
use BarefootEngine\REST\Property_Booking_Checkout_Controller;
use BarefootEngine\REST\Property_Booking_Controller;
use BarefootEngine\REST\Properties_Controller;
use BarefootEngine\REST\Updates_Controller;
use BarefootEngine\Services\Api_Integration_Settings;
use BarefootEngine\Services\Barefoot_Api_Client;
use BarefootEngine\Services\General_Settings;
use BarefootEngine\Services\Property_Sync_Service;
use BarefootEngine\Services\Updates_Service;
use BarefootEngine\Widgets\Booking\Booking_Widget_Preset_Registry;
use BarefootEngine\Widgets\Booking\Booking_Widget_Shortcode;
use BarefootEngine\Widgets\BookingCheckout\Booking_Checkout_Preset_Registry;
use BarefootEngine\Widgets\BookingCheckout\Booking_Checkout_Shortcode;
use BarefootEngine\Widgets\Listings\Listings_Preset_Registry;
use BarefootEngine\Widgets\Listings\Listings_Shortcode;
use BarefootEngine\Widgets\Pricing\Pricing_Table_Shortcode;
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
    private ?Property_Delta_Refresh_Service $property_delta_refresh_service = null;
    private ?Property_Booking_Checkout_Service $property_booking_checkout_service = null;
    private ?Property_Booking_Records $property_booking_records = null;

    public function __construct()
    {
        $this->loader = new Loader();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_property_hooks();
        $this->define_rest_hooks();
        $this->define_integration_hooks();
        $this->define_cron_hooks();
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
        $booking_preset_registry = new Booking_Widget_Preset_Registry();
        $booking_checkout_preset_registry = new Booking_Checkout_Preset_Registry();
        $property_listings_provider = new Property_Listings_Provider($this->get_property_availability_service());
        $listings_shortcode = new Listings_Shortcode(
            $listings_preset_registry,
            $property_listings_provider,
            $search_preset_registry
        );
        $shortcode = new Search_Widget_Shortcode($search_preset_registry);
        $booking_shortcode = new Booking_Widget_Shortcode($booking_preset_registry);
        $booking_checkout_shortcode = new Booking_Checkout_Shortcode(
            $booking_checkout_preset_registry,
            $this->get_property_booking_checkout_service()
        );
        $pricing_table_shortcode = new Pricing_Table_Shortcode();

        $this->loader->add_action('init', $listings_shortcode, 'register', 10, 0);
        $this->loader->add_action('init', $shortcode, 'register', 10, 0);
        $this->loader->add_action('init', $booking_shortcode, 'register', 10, 0);
        $this->loader->add_action('init', $booking_checkout_shortcode, 'register', 10, 0);
        $this->loader->add_action('init', $pricing_table_shortcode, 'register', 10, 0);
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
        $delta_refresh_service = $this->get_property_delta_refresh_service();
        $properties_controller = new Properties_Controller($this->get_property_sync_service(), $delta_refresh_service);
        $availability_controller = new Property_Availability_Controller($this->get_property_availability_service(), $delta_refresh_service);
        $booking_controller = new Property_Booking_Controller();
        $booking_checkout_controller = new Property_Booking_Checkout_Controller($this->get_property_booking_checkout_service());

        $this->loader->add_action('rest_api_init', $controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $general_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $updates_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $properties_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $availability_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $booking_controller, 'register_routes', 10, 0);
        $this->loader->add_action('rest_api_init', $booking_checkout_controller, 'register_routes', 10, 0);
    }

    private function define_property_hooks(): void
    {
        $post_type = new Property_Post_Type();
        $taxonomies = $this->get_property_taxonomies();
        $booking_records = $this->get_property_booking_records();
        $metaboxes = new Property_Metaboxes();
        $admin_actions = new Property_Admin_Actions($this->get_property_sync_service());

        $this->loader->add_action('init', $post_type, 'register', 10, 0);
        $this->loader->add_action('init', $taxonomies, 'register', 10, 0);
        $this->loader->add_action('init', $booking_records, 'register', 10, 0);
        $this->loader->add_action('add_meta_boxes_' . Property_Post_Type::POST_TYPE, $metaboxes, 'register', 10, 0);
        $this->loader->add_action('add_meta_boxes_' . Property_Booking_Records::POST_TYPE, $booking_records, 'register_metaboxes', 10, 0);
        $this->loader->add_filter('manage_edit-' . Property_Booking_Records::POST_TYPE . '_columns', $booking_records, 'filter_columns', 10, 1);
        $this->loader->add_action('manage_' . Property_Booking_Records::POST_TYPE . '_posts_custom_column', $booking_records, 'render_column', 10, 2);
        $this->loader->add_action('admin_post_barefoot_engine_sync_property', $admin_actions, 'sync_single_property', 10, 0);
        $this->loader->add_action('admin_notices', $admin_actions, 'render_admin_notice', 10, 0);
    }

    private function define_integration_hooks(): void
    {
        $updater = new Github_Updater();

        $this->loader->add_action('plugins_loaded', $updater, 'register', 20);
    }

    private function define_cron_hooks(): void
    {
        $delta_refresh_service = $this->get_property_delta_refresh_service();

        $this->loader->add_filter('cron_schedules', $delta_refresh_service, 'register_cron_schedule', 10, 1);
        $this->loader->add_action('init', $delta_refresh_service, 'ensure_scheduled_event', 20, 0);
        $this->loader->add_action(Property_Delta_Refresh_Service::CRON_HOOK, $delta_refresh_service, 'run_scheduled_event', 10, 0);
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

    private function get_property_delta_refresh_service(): Property_Delta_Refresh_Service
    {
        if (!$this->property_delta_refresh_service instanceof Property_Delta_Refresh_Service) {
            $this->property_delta_refresh_service = new Property_Delta_Refresh_Service(
                $this->get_api_client(),
                $this->get_api_settings(),
                null,
                $this->get_property_sync_service(),
                $this->get_property_availability_service()
            );
        }

        return $this->property_delta_refresh_service;
    }

    private function get_property_booking_checkout_service(): Property_Booking_Checkout_Service
    {
        if (!$this->property_booking_checkout_service instanceof Property_Booking_Checkout_Service) {
            $this->property_booking_checkout_service = new Property_Booking_Checkout_Service(
                $this->get_api_client(),
                $this->get_api_settings(),
                null,
                $this->get_property_booking_records()
            );
        }

        return $this->property_booking_checkout_service;
    }

    private function get_property_booking_records(): Property_Booking_Records
    {
        if (!$this->property_booking_records instanceof Property_Booking_Records) {
            $this->property_booking_records = new Property_Booking_Records();
        }

        return $this->property_booking_records;
    }
}
