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

    public function create(string $nome, string $descrizione): bool {
        global $wpdb;
        return (bool) $wpdb->insert($this->table, ['nome' => $nome, 'descrizione' => $descrizione, 'attivo' => 1], ['%s', '%s', '%d']);
    }
}
