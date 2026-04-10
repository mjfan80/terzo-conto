<?php
/**
 * Plugin Name: TerzoConto – Rendiconto ETS per WordPress
 * Description: Registro di cassa semplificato per ETS con report automatici.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Tested up to: 6.9
 * Author: TerzoConto Contributors
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: terzo-conto
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('TERZOCONTO_VERSION', '1.0.0');
define('TERZOCONTO_DB_VERSION', '1.0.0');
define('TERZOCONTO_PLUGIN_FILE', __FILE__);
define('TERZOCONTO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TERZOCONTO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once TERZOCONTO_PLUGIN_DIR . 'includes/class-terzoconto-installer.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/class-terzoconto-activator.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/class-terzoconto.php';

register_activation_hook(__FILE__, ['TerzoConto_Activator', 'activate']);

function terzoconto_handle_upgrade($upgrader_object, array $options): void {
    if (($options['action'] ?? '') !== 'update') {
        return;
    }

    if (! isset($options['type']) || $options['type'] !== 'plugin') {
        return;
    }

    $plugin_basename = plugin_basename(__FILE__);
    $updated_plugins = [];

    if (isset($options['plugins']) && is_array($options['plugins'])) {
        $updated_plugins = $options['plugins'];
    } elseif (isset($options['plugin']) && is_string($options['plugin'])) {
        $updated_plugins = [$options['plugin']];
    }

    if (! in_array($plugin_basename, $updated_plugins, true)) {
        return;
    }

    TerzoConto_Installer::install_or_update();
}
add_action('upgrader_process_complete', 'terzoconto_handle_upgrade', 10, 2);

function terzoconto_maybe_run_version_check(): void {
    $stored_db_version = get_option('terzoconto_db_version', '');
    $stored_plugin_version = get_option('terzoconto_plugin_version', '');

    if (
        version_compare((string) $stored_db_version, TERZOCONTO_DB_VERSION, '<')
        || version_compare((string) $stored_plugin_version, TERZOCONTO_VERSION, '<')
    ) {
        TerzoConto_Installer::install_or_update();
    }
}
add_action('plugins_loaded', 'terzoconto_maybe_run_version_check', 5);

function terzoconto_bootstrap(): void {
    $plugin = new TerzoConto();
    $plugin->run();
}
add_action('plugins_loaded', 'terzoconto_bootstrap');
