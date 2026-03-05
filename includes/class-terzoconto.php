<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-movimenti-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-categorie-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-conti-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-raccolte-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/services/class-terzoconto-import-service.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/services/class-terzoconto-report-service.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-admin.php';

class TerzoConto {
    public function run(): void {
        add_action('init', ['TerzoConto_Activator', 'register_attachment_taxonomy']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        if (is_admin()) {
            $admin = new TerzoConto_Admin(
                new TerzoConto_Movimenti_Repository(),
                new TerzoConto_Categorie_Repository(),
                new TerzoConto_Conti_Repository(),
                new TerzoConto_Raccolte_Repository(),
                new TerzoConto_Import_Service(),
                new TerzoConto_Report_Service()
            );
            $admin->hooks();
        }
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('terzo-conto', false, dirname(plugin_basename(TERZOCONTO_PLUGIN_FILE)) . '/languages');
    }
}
