<?php
/**
 * Plugin Name: TerzoConto – Rendiconto ETS per WordPress
 * Description: Registro di cassa semplificato per ETS con report automatici.
 * Version: 0.1.0
 * Author: TerzoConto Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: terzo-conto
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TERZOCONTO_VERSION', '0.1.0');
define('TERZOCONTO_PLUGIN_FILE', __FILE__);
define('TERZOCONTO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TERZOCONTO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TERZOCONTO_PLUGIN_DIR . 'includes/class-terzoconto-activator.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/class-terzoconto.php';

register_activation_hook(__FILE__, ['TerzoConto_Activator', 'activate']);

function terzoconto_bootstrap(): void {
    $plugin = new TerzoConto();
    $plugin->run();
}
add_action('plugins_loaded', 'terzoconto_bootstrap');
