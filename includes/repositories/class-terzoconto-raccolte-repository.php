<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Raccolte_Repository {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'terzoconto_raccolte_fondi';
    }

    public function get_all(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY data_inizio DESC", ARRAY_A) ?: [];
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
        ], ['%s', '%s', '%s', '%s', '%s']);
    }

    public function is_open(int $id): bool {
        global $wpdb;
        $status = $wpdb->get_var($wpdb->prepare("SELECT stato FROM {$this->table} WHERE id = %d", $id));
        return $status === 'aperta';
    }
}
