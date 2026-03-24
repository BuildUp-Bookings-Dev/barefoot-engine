<?php

namespace BarefootEngine\Core;

use BarefootEngine\Properties\Property_Delta_Refresh_Service;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    /**
     * Run plugin activation tasks.
     */
    public static function activate(): void
    {
        if (!get_option('barefoot_engine_options')) {
            add_option(
                'barefoot_engine_options',
                [
                    'api_base_url' => '',
                    'api_key' => '',
                ]
            );
        }

        if (!wp_next_scheduled(Property_Delta_Refresh_Service::CRON_HOOK)) {
            wp_schedule_single_event(time() + 120, Property_Delta_Refresh_Service::CRON_HOOK);
        }

        Booking_Confirmation_Page::register_rewrite_rules_static();
        flush_rewrite_rules();
    }
}
