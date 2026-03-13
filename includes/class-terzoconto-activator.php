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
            area CHAR(1) NOT NULL,
            numero INT NOT NULL,
            tipo ENUM('E','U') NOT NULL,
            codice VARCHAR(10) NOT NULL,
            nome VARCHAR(191) NOT NULL,
            ordinamento INT NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY unique_voce (area, numero, tipo)
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
            anagrafica_id BIGINT UNSIGNED NULL,
            descrizione TEXT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            stato VARCHAR(20) NOT NULL DEFAULT 'attivo',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY anno_progressivo (anno, progressivo_annuale),
            KEY categoria_associazione_id (categoria_associazione_id),
            KEY conto_id (conto_id),
            KEY raccolta_fondi_id (raccolta_fondi_id),
            KEY anagrafica_id (anagrafica_id)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_anagrafiche (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tipo VARCHAR(20) NOT NULL DEFAULT 'persona',
            codice_fiscale VARCHAR(16) NULL,
            partita_iva VARCHAR(11) NULL,
            nome VARCHAR(100) NULL,
            cognome VARCHAR(100) NULL,
            ragione_sociale VARCHAR(200) NULL,
            email VARCHAR(200) NULL,
            telefono VARCHAR(50) NULL,
            indirizzo VARCHAR(200) NULL,
            cap VARCHAR(10) NULL,
            comune VARCHAR(100) NULL,
            provincia VARCHAR(2) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY codice_fiscale (codice_fiscale)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_comunicazioni_ae (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            anno INT NOT NULL,
            data_generazione DATETIME NULL,
            file_path VARCHAR(255) NULL,
            record_count INT NULL,
            utente_id BIGINT UNSIGNED NULL,
            created_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY anno (anno)
        ) {$charset_collate};";

        $sql[] = "CREATE TABLE {$prefix}terzoconto_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome_ente VARCHAR(255) NULL,
            codice_fiscale VARCHAR(16) NULL,
            partita_iva VARCHAR(11) NULL,
            numero_runts VARCHAR(50) NULL,
            indirizzo VARCHAR(255) NULL,
            email VARCHAR(200) NULL,
            telefono VARCHAR(50) NULL,
            logo_url VARCHAR(255) NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id)
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
            // Uscite (U)
            ['A', 1, 'U', __('Materie prime, sussidiarie, di consumo e di merci', 'terzo-conto')],
            ['A', 2, 'U', __('Servizi', 'terzo-conto')],
            ['A', 3, 'U', __('Godimento beni di terzi', 'terzo-conto')],
            ['A', 4, 'U', __('Personale', 'terzo-conto')],
            ['A', 5, 'U', __('Uscite diverse di gestione', 'terzo-conto')],
            ['B', 1, 'U', __('Materie prime, sussidiarie, di consumo e di merci', 'terzo-conto')],
            ['B', 2, 'U', __('Servizi', 'terzo-conto')],
            ['B', 3, 'U', __('Godimento beni di terzi', 'terzo-conto')],
            ['B', 4, 'U', __('Personale', 'terzo-conto')],
            ['B', 5, 'U', __('Uscite diverse di gestione', 'terzo-conto')],
            ['C', 1, 'U', __('Uscite per raccolte fondi abituali', 'terzo-conto')],
            ['C', 2, 'U', __('Uscite per raccolte fondi occasionali', 'terzo-conto')],
            ['C', 3, 'U', __('Altre uscite', 'terzo-conto')],
            ['D', 1, 'U', __('Su rapporti bancari', 'terzo-conto')],
            ['D', 2, 'U', __('Su investimenti finanziari', 'terzo-conto')],
            ['D', 3, 'U', __('Su patrimonio edilizio', 'terzo-conto')],
            ['D', 4, 'U', __('Su altri beni patrimoniali', 'terzo-conto')],
            ['D', 5, 'U', __('Altre uscite', 'terzo-conto')],
            ['E', 1, 'U', __('Materie prime, sussidiarie, di consumo e di merci', 'terzo-conto')],
            ['E', 2, 'U', __('Servizi', 'terzo-conto')],
            ['E', 3, 'U', __('Godimento beni di terzi', 'terzo-conto')],
            ['E', 4, 'U', __('Personale', 'terzo-conto')],
            ['E', 5, 'U', __('Altre uscite', 'terzo-conto')],

            // Entrate (E)
            ['A', 1, 'E', __('Entrate da quote associative e apporti dei fondatori', 'terzo-conto')],
            ['A', 2, 'E', __('Entrate dagli associati per attività mutuali', 'terzo-conto')],
            ['A', 3, 'E', __('Entrate per prestazioni e cessioni ad associati e fondatori', 'terzo-conto')],
            ['A', 4, 'E', __('Erogazioni liberali', 'terzo-conto')],
            ['A', 5, 'E', __('Entrate del 5 per mille', 'terzo-conto')],
            ['A', 6, 'E', __('Contributi da soggetti privati', 'terzo-conto')],
            ['A', 7, 'E', __('Entrate per prestazioni e cessioni a terzi', 'terzo-conto')],
            ['A', 8, 'E', __('Contributi da enti pubblici', 'terzo-conto')],
            ['A', 9, 'E', __('Entrate da contratti con enti pubblici', 'terzo-conto')],
            ['A', 10, 'E', __('Altre entrate', 'terzo-conto')],
            ['B', 1, 'E', __('Entrate per prestazioni e cessioni ad associati e fondatori', 'terzo-conto')],
            ['B', 2, 'E', __('Contributi da soggetti privati', 'terzo-conto')],
            ['B', 3, 'E', __('Entrate per prestazioni e cessioni a terzi', 'terzo-conto')],
            ['B', 4, 'E', __('Contributi da enti pubblici', 'terzo-conto')],
            ['B', 5, 'E', __('Entrate da contratti con enti pubblici', 'terzo-conto')],
            ['B', 6, 'E', __('Altre entrate', 'terzo-conto')],
            ['C', 1, 'E', __('Entrate da raccolte fondi abituali', 'terzo-conto')],
            ['C', 2, 'E', __('Entrate da raccolte fondi occasionali', 'terzo-conto')],
            ['C', 3, 'E', __('Altre entrate', 'terzo-conto')],
            ['D', 1, 'E', __('Da rapporti bancari', 'terzo-conto')],
            ['D', 2, 'E', __('Da altri investimenti finanziari', 'terzo-conto')],
            ['D', 3, 'E', __('Da patrimonio edilizio', 'terzo-conto')],
            ['D', 4, 'E', __('Da altri beni patrimoniali', 'terzo-conto')],
            ['D', 5, 'E', __('Altre entrate', 'terzo-conto')],
            ['E', 1, 'E', __('Entrate da distacco del personale', 'terzo-conto')],
            ['E', 2, 'E', __('Altre entrate di supporto generale', 'terzo-conto')],
        ];

        foreach ($model_d as $index => $item) {
            $codice = $item[0] . (string) $item[1];
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT IGNORE INTO {$prefix}terzoconto_categorie_modello_d (area, numero, tipo, codice, nome, ordinamento) VALUES (%s, %d, %s, %s, %s, %d)",
                    $item[0],
                    $item[1],
                    $item[2],
                    $codice,
                    $item[3],
                    $index + 1
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
