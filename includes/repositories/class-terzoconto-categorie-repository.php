<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Categorie_Repository {
    private string $table_assoc;
    private string $table_modeld;

    public function __construct() {
        global $wpdb;
        $this->table_assoc = $wpdb->prefix . 'terzoconto_categorie_associazione';
        $this->table_modeld = $wpdb->prefix . 'terzoconto_categorie_modello_d';
    }

    public function get_associazione(): array {
        global $wpdb;
        $sql = "SELECT ca.*, md.nome AS modello_d_nome, md.codice AS modello_d_codice FROM {$this->table_assoc} ca INNER JOIN {$this->table_modeld} md ON md.id = ca.modello_d_id ORDER BY ca.nome ASC";
        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function get_modello_d(): array {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table_modeld} ORDER BY tipo ASC, codice ASC", ARRAY_A) ?: [];
    }

    public function create_associazione(string $nome, int $modello_d_id, string $descrizione): bool {
        global $wpdb;
        return (bool) $wpdb->insert(
            $this->table_assoc,
            ['nome' => $nome, 'modello_d_id' => $modello_d_id, 'descrizione' => $descrizione, 'attiva' => 1],
            ['%s', '%d', '%s', '%d']
        );
    }
}
