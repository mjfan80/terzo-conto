<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Activator {
    public static function activate(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql = [];

        $sql[] = "CREATE TABLE {$prefix}terzoconto_categorie_modello_d (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            codice VARCHAR(50) NOT NULL,
            nome VARCHAR(191) NOT NULL,
            tipo VARCHAR(20) NOT NULL,
            ordinamento INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY codice_unico (codice)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_categorie_associazione (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(191) NOT NULL,
            modello_d_id BIGINT UNSIGNED NOT NULL,
            descrizione TEXT NULL,
            attiva TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY modello_d_id (modello_d_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_conti (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(191) NOT NULL,
            descrizione TEXT NULL,
            attivo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_raccolte_fondi (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(191) NOT NULL,
            descrizione TEXT NULL,
            data_inizio DATE NOT NULL,
            data_fine DATE NULL,
            stato VARCHAR(20) NOT NULL DEFAULT 'aperta',
            PRIMARY KEY (id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_movimenti (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            progressivo_annuale INT UNSIGNED NOT NULL,
            anno SMALLINT UNSIGNED NOT NULL,
            data_movimento DATE NOT NULL,
            importo DECIMAL(12,2) NOT NULL,
            tipo VARCHAR(10) NOT NULL,
            categoria_associazione_id BIGINT UNSIGNED NOT NULL,
            conto_id BIGINT UNSIGNED NOT NULL,
            raccolta_fondi_id BIGINT UNSIGNED NULL,
            descrizione TEXT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            stato VARCHAR(20) NOT NULL DEFAULT 'attivo',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY anno_progressivo (anno, progressivo_annuale),
            KEY categoria_associazione_id (categoria_associazione_id),
            KEY conto_id (conto_id),
            KEY raccolta_fondi_id (raccolta_fondi_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_regole (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(191) NOT NULL,
            tipo_import VARCHAR(30) NOT NULL,
            criterio TEXT NOT NULL,
            categoria_associazione_id BIGINT UNSIGNED NULL,
            conto_id BIGINT UNSIGNED NULL,
            priorita INT NOT NULL DEFAULT 0,
            attiva TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        self::seed_defaults($prefix);
        self::register_attachment_taxonomy();
        flush_rewrite_rules();
    }

    private static function seed_defaults(string $prefix): void {
        global $wpdb;

        $model_d = [
            ['E1', __('Entrate da quote associative', 'terzo-conto'), 'entrata'],
            ['E2', __('Entrate da raccolte fondi', 'terzo-conto'), 'entrata'],
            ['U1', __('Uscite per attività istituzionali', 'terzo-conto'), 'uscita'],
            ['U2', __('Uscite generali', 'terzo-conto'), 'uscita'],
        ];

        foreach ($model_d as $item) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$prefix}terzoconto_categorie_modello_d (codice, nome, tipo, ordinamento) VALUES (%s, %s, %s, %d)",
                    $item[0],
                    $item[1],
                    $item[2],
                    0
                )
            );
        }

        $accounts = ['Cassa', 'Conto corrente', 'PayPal', 'Satispay'];
        foreach ($accounts as $account) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}terzoconto_conti WHERE nome = %s", $account));
            if (! $exists) {
                $wpdb->insert("{$prefix}terzoconto_conti", ['nome' => $account, 'attivo' => 1], ['%s', '%d']);
            }
        }
    }

    public static function register_attachment_taxonomy(): void {
        register_taxonomy('terzoconto_allegato_movimento', 'attachment', [
            'labels' => [
                'name' => __('Allegati TerzoConto', 'terzo-conto'),
                'singular_name' => __('Allegato Movimento', 'terzo-conto'),
            ],
            'public' => false,
            'show_ui' => false,
            'rewrite' => false,
            'hierarchical' => false,
        ]);
    }
}
