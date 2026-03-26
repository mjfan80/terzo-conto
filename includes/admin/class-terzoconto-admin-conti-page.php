<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Admin_Conti_Page {
    public function render(TerzoConto_Admin $admin, array $context): void {
        include TERZOCONTO_PLUGIN_DIR . 'includes/admin/templates/conti-page.php';
    }
}
