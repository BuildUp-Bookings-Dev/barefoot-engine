<?php

namespace BarefootEngine\Includes;

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

        flush_rewrite_rules();
    }
}
