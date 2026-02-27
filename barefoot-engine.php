<?php
/**
 * Plugin Name: Barefoot Engine
 * Plugin URI:  http://braudyp.dev/
 * Description: Barefoot API full vacation rental integration.
 * Version:     0.1.0
 * Author:      Braudy Pedrosa
 * Author URI:  https://buildupbookings.com/
 * Text Domain: barefoot-engine
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAREFOOT_ENGINE_VERSION', '0.1.0');
define('BAREFOOT_ENGINE_PLUGIN_FILE', __FILE__);
define('BAREFOOT_ENGINE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('BAREFOOT_ENGINE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BAREFOOT_ENGINE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BAREFOOT_ENGINE_GITHUB_REPOSITORY', 'https://github.com/BuildUp-Bookings-Dev/barefoot-engine');
define('BAREFOOT_ENGINE_GITHUB_BRANCH', 'main');

require_once BAREFOOT_ENGINE_PLUGIN_DIR . 'includes/class-autoloader.php';

BarefootEngine\Includes\Autoloader::register();

register_activation_hook(__FILE__, ['BarefootEngine\\Includes\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['BarefootEngine\\Includes\\Deactivator', 'deactivate']);

function barefoot_engine_bootstrap(): void
{
    $vendor_autoload = BAREFOOT_ENGINE_PLUGIN_DIR . 'libraries/vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }

    $plugin = new BarefootEngine\Includes\Plugin();
    $plugin->run();
}

barefoot_engine_bootstrap();
