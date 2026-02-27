<?php
/**
 * Uninstall Barefoot Engine.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('barefoot_engine_options');
