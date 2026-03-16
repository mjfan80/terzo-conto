<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Conti_Repository {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'terzoconto_conti';
    }

    public function get_all(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY nome ASC", ARRAY_A) ?: [];
    }

    public function find_by_id(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function create(string $nome, string $descrizione, int $tracciabile = 0, int $attivo = 1): bool {
        global $wpdb;
        return (bool) $wpdb->insert(
            $this->table,
            [
                'nome' => $nome,
                'descrizione' => $descrizione,
                'tracciabile' => $tracciabile,
                'attivo' => $attivo,
            ],
            ['%s', '%s', '%d', '%d']
        );
    }

    public function update(int $id, string $nome, string $descrizione, int $tracciabile, int $attivo): bool {
        global $wpdb;
        return false !== $wpdb->update(
            $this->table,
            [
                'nome' => $nome,
                'descrizione' => $descrizione,
                'tracciabile' => $tracciabile,
                'attivo' => $attivo,
            ],
            ['id' => $id],
            ['%s', '%s', '%d', '%d'],
            ['%d']
        );
    }
}
