<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-movimenti-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-categorie-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-conti-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-raccolte-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-anagrafiche-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/repositories/class-terzoconto-settings-repository.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/services/class-terzoconto-import-service.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/services/class-terzoconto-report-service.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/services/class-terzoconto-movimenti-service.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-admin.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-admin-anagrafiche.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-admin-settings.php';

class TerzoConto {
    public function run(): void {
        add_action('init', ['TerzoConto_Activator', 'register_attachment_taxonomy']);
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        if (is_admin()) {
            $movimenti_repository = new TerzoConto_Movimenti_Repository();
            $raccolte_repository = new TerzoConto_Raccolte_Repository();

            $admin = new TerzoConto_Admin(
                $movimenti_repository,
                new TerzoConto_Movimenti_Service($movimenti_repository, $raccolte_repository),
                new TerzoConto_Categorie_Repository(),
                new TerzoConto_Conti_Repository(),
                $raccolte_repository,
                new TerzoConto_Anagrafiche_Repository(),
                new TerzoConto_Import_Service(),
                new TerzoConto_Report_Service()
            );
            $admin->hooks();

            $admin_anagrafiche = new TerzoConto_Admin_Anagrafiche(
                new TerzoConto_Anagrafiche_Repository()
            );
            $admin_anagrafiche->hooks();

            $admin_settings = new TerzoConto_Admin_Settings(
                new TerzoConto_Settings_Repository()
            );
            $admin_settings->hooks();
        }
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('terzoconto-rendiconto-ets', false, dirname(plugin_basename(TERZOCONTO_PLUGIN_FILE)) . '/languages');
    }
}
