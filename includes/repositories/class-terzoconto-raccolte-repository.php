<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Raccolte_Repository {
    private string $table;
    private string $movimenti_table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'terzoconto_raccolte_fondi';
        $this->movimenti_table = $wpdb->prefix . 'terzoconto_movimenti';
    }

    public function get_all(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY (stato = 'aperta') DESC, data_inizio DESC", ARRAY_A) ?: [];
    }

    public function find_by_id(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function get_aperte(): array {
        global $wpdb;
        $sql = $wpdb->prepare("SELECT * FROM {$this->table} WHERE stato = %s ORDER BY data_inizio DESC", 'aperta');
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function create(array $data): bool {
        global $wpdb;
        return (bool) $wpdb->insert($this->table, [
            'nome' => $data['nome'],
            'descrizione' => $data['descrizione'],
            'data_inizio' => $data['data_inizio'],
            'data_fine' => $data['data_fine'] ?: null,
            'stato' => $data['stato'],
            'relazione_illustrativa' => $data['relazione_illustrativa'] ?: null,
        ], ['%s', '%s', '%s', '%s', '%s', '%s']);
    }

    public function update(int $id, array $data): bool {
        global $wpdb;
        return false !== $wpdb->update(
            $this->table,
            [
                'nome' => $data['nome'],
                'descrizione' => $data['descrizione'],
                'data_inizio' => $data['data_inizio'],
                'data_fine' => $data['data_fine'] ?: null,
                'stato' => $data['stato'],
                'relazione_illustrativa' => $data['relazione_illustrativa'] ?: null,
            ],
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    public function count_movimenti(int $raccolta_id): int {
        global $wpdb;
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->movimenti_table} WHERE raccolta_fondi_id = %d",
                $raccolta_id
            )
        );
    }

    public function can_delete(int $raccolta_id): bool {
        return $this->count_movimenti($raccolta_id) === 0;
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

    public function is_open(int $id): bool {
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare("SELECT stato FROM {$this->table} WHERE id = %d", $id));
        return $status === 'aperta';
    }
}
