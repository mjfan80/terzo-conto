<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Installer {
    private const DB_VERSION_OPTION = 'terzoconto_db_version';
    private const PLUGIN_VERSION_OPTION = 'terzoconto_plugin_version';
    private const SEED_VERSION_OPTION = 'terzoconto_seed_version';
    private const MIGRATION_LOCK_OPTION = 'terzoconto_migrating';

    public static function install(): void {
        $current_version = get_option(self::DB_VERSION_OPTION, false);

        try {
            if (
                $current_version !== false
                && version_compare((string) $current_version, TERZOCONTO_DB_VERSION, '<')
            ) {
                self::install_or_update();
                return;
            }

            self::register_attachment_taxonomy();
            self::maybe_create_tables();
            self::maybe_seed_defaults();
            update_option(self::DB_VERSION_OPTION, TERZOCONTO_DB_VERSION, false);
            update_option(self::PLUGIN_VERSION_OPTION, TERZOCONTO_VERSION, false);
        } catch (Throwable $exception) {
            error_log(sprintf('[TerzoConto] Activation install failed at DB version %s: %s', (string) $current_version, $exception->getMessage()));
        }
    }

    public static function install_or_update(): void {
        $current_version = get_option(self::DB_VERSION_OPTION, false);
        $current_plugin_version = get_option(self::PLUGIN_VERSION_OPTION, false);

        try {
            if ($current_version === false) {
                self::maybe_create_tables();
                self::maybe_seed_defaults();
                self::register_attachment_taxonomy();

                update_option(self::DB_VERSION_OPTION, TERZOCONTO_DB_VERSION, false);
                update_option(self::PLUGIN_VERSION_OPTION, TERZOCONTO_VERSION, false);

                return;
            }

            if (version_compare((string) $current_version, TERZOCONTO_DB_VERSION, '<')) {
                self::maybe_create_tables();

                $migrations_ran = self::run_migrations((string) $current_version);

                if (! $migrations_ran) {
                    return;
                }

                self::maybe_seed_defaults();
                update_option(self::DB_VERSION_OPTION, TERZOCONTO_DB_VERSION, false);
            }

            if (version_compare((string) $current_plugin_version, TERZOCONTO_VERSION, '<')) {
                update_option(self::PLUGIN_VERSION_OPTION, TERZOCONTO_VERSION, false);
            }

            self::register_attachment_taxonomy();
        } catch (Throwable $exception) {
            error_log(sprintf('[TerzoConto] Upgrade failed from DB version %s to %s: %s', (string) $current_version, TERZOCONTO_DB_VERSION, $exception->getMessage()));
        }
    }

    private static function maybe_create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $tables = self::get_schema_statements($prefix, $charset_collate);

        foreach ($tables as $statement) {
            dbDelta($statement);
        }
    }

    private static function get_migrations(): array {
        return [
            // v1.1.0: aggiunge updated_at e indice su (anno, progressivo_annuale) nella tabella movimenti.
            '1.1.0' => static function (): void {
                global $wpdb;

                $table = $wpdb->prefix . 'terzoconto_movimenti';

                if (! self::table_exists($table)) {
                    return;
                }

                if (! self::column_exists($table, 'updated_at')) {
                   $table = esc_sql($table);

                    $result = $wpdb->query(
                        "ALTER TABLE {$table} ADD COLUMN updated_at DATETIME NOT NULL AFTER created_at"
                    );
                    if ($result === false) {
                        throw new RuntimeException(sprintf('Unable to add column updated_at to %s: %s', $table, $wpdb->last_error));
                    }
                }

                if (! self::index_exists($table, 'anno_progressivo')) {
                    $table = esc_sql($table);
                    $result = $wpdb->query(
                        "ALTER TABLE {$table} ADD INDEX anno_progressivo (anno, progressivo_annuale)"
                    );
                    if ($result === false) {
                        throw new RuntimeException(sprintf('Unable to add index anno_progressivo to %s: %s', $table, $wpdb->last_error));
                    }
                }
            },
            // v1.2.0: aggiunge codice_fiscale e relativo indice alla tabella anagrafiche.
            '1.2.0' => static function (): void {
                global $wpdb;

                $table = $wpdb->prefix . 'terzoconto_anagrafiche';

                if (! self::table_exists($table)) {
                    return;
                }

                if (! self::column_exists($table, 'codice_fiscale')) {
                    $table = esc_sql($table); 
                    $result = $wpdb->query( "ALTER TABLE {$table} ADD COLUMN codice_fiscale VARCHAR(16) NULL AFTER tipo" );
                    if ($result === false) {
                        throw new RuntimeException(sprintf('Unable to add column codice_fiscale to %s: %s', $table, $wpdb->last_error));
                    }
                }

                if (! self::index_exists($table, 'codice_fiscale')) {
                    $table = esc_sql($table);
                    $result = $wpdb->query(
                        "ALTER TABLE {$table} ADD INDEX codice_fiscale (codice_fiscale)"
                    );
                    if ($result === false) {
                        throw new RuntimeException(sprintf('Unable to add index codice_fiscale to %s: %s', $table, $wpdb->last_error));
                    }
                }
            },
        ];
    }

    private static function run_migrations(string $current_version): bool {
        $lock_acquired = add_option(self::MIGRATION_LOCK_OPTION, 1, '', 'no');

        if (! $lock_acquired) {
            return false;
        }

        try {
            $migrations = self::get_migrations();
            ksort($migrations, SORT_NATURAL);

            foreach ($migrations as $version => $migration) {
                if (version_compare($current_version, $version, '<')) {
                    try {
                        $migration();
                    } catch (Throwable $exception) {
                        error_log(sprintf('[TerzoConto] Migration %s failed: %s', $version, $exception->getMessage()));
                        throw $exception;
                    }
                }
            }

            return true;
        } finally {
            delete_option(self::MIGRATION_LOCK_OPTION);
        }
    }

    private static function table_exists(string $table_name): bool {
        global $wpdb;

        $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $table_name);
        $found = $wpdb->get_var($sql);

        return $found === $table_name;
    }

    private static function required_tables_exist(): bool {
        global $wpdb;

        $required_tables = [
            $wpdb->prefix . 'terzoconto_movimenti',
            $wpdb->prefix . 'terzoconto_categorie_associazione',
            $wpdb->prefix . 'terzoconto_categorie_modello_d',
            $wpdb->prefix . 'terzoconto_conti',
            $wpdb->prefix . 'terzoconto_raccolte_fondi',
            $wpdb->prefix . 'terzoconto_anagrafiche',
            $wpdb->prefix . 'terzoconto_settings',
            $wpdb->prefix . 'terzoconto_comunicazioni_ae',
            $wpdb->prefix . 'terzoconto_regole',
        ];

        foreach ($required_tables as $table_name) {
            if (! self::table_exists($table_name)) {
                return false;
            }
        }

        return true;
    }

    private static function column_exists(string $table_name, string $column_name): bool {
        global $wpdb;

        if (! self::table_exists($table_name)) {
            return false;
        }

        $sql = $wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $column_name);
        $result = $wpdb->get_var($sql);

        return $result === $column_name;
    }

    private static function index_exists(string $table_name, string $index_name): bool {
        global $wpdb;

        if (! self::table_exists($table_name)) {
            return false;
        }

        $sql = $wpdb->prepare("SHOW INDEX FROM {$table_name} WHERE Key_name = %s", $index_name);
        $result = $wpdb->get_var($sql);

        return $result === $index_name;
    }

    private static function get_schema_statements(string $prefix, string $charset_collate): array {
        $tables = [];

        $tables["{$prefix}terzoconto_categorie_modello_d"] = "CREATE TABLE {$prefix}terzoconto_categorie_modello_d (
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

        $tables["{$prefix}terzoconto_categorie_associazione"] = "CREATE TABLE {$prefix}terzoconto_categorie_associazione (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(191) NOT NULL,
            modello_d_id BIGINT UNSIGNED NOT NULL,
            descrizione TEXT NULL,
            attiva TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY modello_d_id (modello_d_id)
        ) {$charset_collate};";

        $tables["{$prefix}terzoconto_conti"] = "CREATE TABLE {$prefix}terzoconto_conti (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(191) NOT NULL,
            descrizione TEXT NULL,
            tracciabile TINYINT(1) NOT NULL DEFAULT 0,
            attivo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        $tables["{$prefix}terzoconto_raccolte_fondi"] = "CREATE TABLE {$prefix}terzoconto_raccolte_fondi (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(191) NOT NULL,
            descrizione TEXT NULL,
            data_inizio DATE NOT NULL,
            data_fine DATE NULL,
            stato VARCHAR(20) NOT NULL DEFAULT 'aperta',
            relazione_illustrativa TEXT NULL,
            PRIMARY KEY (id)
        ) {$charset_collate};";

        $tables["{$prefix}terzoconto_movimenti"] = "CREATE TABLE {$prefix}terzoconto_movimenti (
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

        $tables["{$prefix}terzoconto_anagrafiche"] = "CREATE TABLE {$prefix}terzoconto_anagrafiche (
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

        $tables["{$prefix}terzoconto_comunicazioni_ae"] = "CREATE TABLE {$prefix}terzoconto_comunicazioni_ae (
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

        $tables["{$prefix}terzoconto_settings"] = "CREATE TABLE {$prefix}terzoconto_settings (
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

        $tables["{$prefix}terzoconto_regole"] = "CREATE TABLE {$prefix}terzoconto_regole (
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

        return $tables;
    }

    private static function maybe_seed_defaults(): void {
        $seeded_version = get_option(self::SEED_VERSION_OPTION, '');

        if (
            $seeded_version !== false
            && version_compare((string) $seeded_version, TERZOCONTO_DB_VERSION, '>=')
        ) {
            return;
        }

        self::seed_defaults();
        update_option(self::SEED_VERSION_OPTION, TERZOCONTO_DB_VERSION, false);
    }

    private static function seed_defaults(): void {
        global $wpdb;

        $categorie_modello_d_table = $wpdb->prefix . 'terzoconto_categorie_modello_d';
        $categorie_associazione_table = $wpdb->prefix . 'terzoconto_categorie_associazione';
        $conti_table = $wpdb->prefix . 'terzoconto_conti';

        $model_d = [
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
                    "INSERT IGNORE INTO {$categorie_modello_d_table} (area, numero, tipo, codice, nome, ordinamento) VALUES (%s, %d, %s, %s, %s, %d)",
                    $item[0],
                    $item[1],
                    $item[2],
                    $codice,
                    $item[3],
                    $index + 1
                )
            );
        }

        $categorie_assoc_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$categorie_associazione_table}"
        );

        if ($categorie_assoc_count === 0) {
            $model_d_rows = $wpdb->get_results(
                "SELECT id, nome FROM {$categorie_modello_d_table} ORDER BY ordinamento ASC",
                ARRAY_A
            ) ?: [];

            foreach ($model_d_rows as $row) {
                $wpdb->insert(
                    $categorie_associazione_table,
                    [
                        'nome' => $row['nome'],
                        'modello_d_id' => (int) $row['id'],
                        'descrizione' => null,
                        'attiva' => 1,
                    ],
                    ['%s', '%d', '%s', '%d']
                );
            }
        }

        $accounts = ['Cassa', 'Conto corrente', 'PayPal', 'Satispay'];

        foreach ($accounts as $account) {
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$conti_table} WHERE nome = %s",
                    $account
                )
            );

            if (! $exists) {
                $wpdb->insert($conti_table, ['nome' => $account, 'attivo' => 1], ['%s', '%d']);
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
