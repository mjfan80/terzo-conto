<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Activator {
    public static function activate(): void {
        TerzoConto_Installer::install();
        flush_rewrite_rules();
    }

    public static function register_attachment_taxonomy(): void {
        TerzoConto_Installer::register_attachment_taxonomy();
    }
}
