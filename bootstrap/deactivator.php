<?php

namespace BarefootEngine\Core;

use BarefootEngine\Properties\Property_Delta_Refresh_Service;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator
{
    /**
     * Run plugin deactivation tasks.
     */
    public static function deactivate(): void
    {
        Property_Delta_Refresh_Service::clear_scheduled_events();
        flush_rewrite_rules();
    }
}
