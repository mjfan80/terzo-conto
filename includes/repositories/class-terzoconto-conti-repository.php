<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Conti_Repository {
    private string $table;
    private string $movimenti_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'terzoconto_conti';
        $this->movimenti_table = $wpdb->prefix . 'terzoconto_movimenti';
    }

    public function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE %d = %d ORDER BY nome ASC", 1, 1),
            ARRAY_A
        ) ?: [];
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

    public function count_movimenti(int $conto_id): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->movimenti_table} WHERE conto_id = %d",
                $conto_id
            )
        );
    }

    public function can_delete(int $conto_id): bool {
        return $this->count_movimenti($conto_id) === 0;
    }

    public function delete(int $id): bool {
        global $wpdb;

        if (! $this->can_delete($id)) {
            return false;
        }

        return false !== $wpdb->delete(
            $this->table,
            ['id' => $id],
            ['%d']
        );
    }
}
