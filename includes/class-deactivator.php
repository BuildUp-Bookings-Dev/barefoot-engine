<?php

namespace BarefootEngine\Includes;

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
        flush_rewrite_rules();
    }
}
