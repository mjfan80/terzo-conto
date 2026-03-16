<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Anagrafiche_Repository {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'terzoconto_anagrafiche';
    }

    public function create(array $data): int {
        global $wpdb;

        $now = current_time('mysql');
        $inserted = $wpdb->insert(
            $this->table,
            [
                'tipo' => $data['tipo'] ?? 'persona',
                'nome' => $data['nome'] ?? null,
                'cognome' => $data['cognome'] ?? null,
                'ragione_sociale' => $data['ragione_sociale'] ?? null,
                'codice_fiscale' => $data['codice_fiscale'] ?? null,
                'email' => $data['email'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (! $inserted) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;

        return false !== $wpdb->update(
            $this->table,
            [
                'tipo' => $data['tipo'] ?? 'persona',
                'nome' => $data['nome'] ?? null,
                'cognome' => $data['cognome'] ?? null,
                'ragione_sociale' => $data['ragione_sociale'] ?? null,
                'codice_fiscale' => $data['codice_fiscale'] ?? null,
                'email' => $data['email'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    public function find_by_id(int $id): ?array {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function find_by_cf(string $cf): ?array {
        global $wpdb;

        $normalized = strtoupper(trim($cf));
        if ($normalized === '') {
            return null;
        }

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE UPPER(codice_fiscale) = %s LIMIT 1", $normalized),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    public function search(string $term): array {
        global $wpdb;

        $normalized = trim($term);
        if ($normalized === '') {
            return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY cognome ASC, nome ASC, ragione_sociale ASC LIMIT 100", ARRAY_A) ?: [];
        }

        $like = '%' . $wpdb->esc_like($normalized) . '%';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT *
                FROM {$this->table}
                WHERE nome LIKE %s
                    OR cognome LIKE %s
                    OR ragione_sociale LIKE %s
                    OR codice_fiscale LIKE %s
                    OR email LIKE %s
                ORDER BY cognome ASC, nome ASC, ragione_sociale ASC
                LIMIT 100",
                $like,
                $like,
                $like,
                $like,
                $like
            ),
            ARRAY_A
        ) ?: [];
    }
}
