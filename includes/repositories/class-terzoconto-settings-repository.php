<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Settings_Repository {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'terzoconto_settings';
    }

    public function get(): ?array {
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$this->table} ORDER BY id ASC LIMIT 1", ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function save(array $data): bool {
        global $wpdb;

        $existing = $this->get();
        $payload = [
            'nome_ente' => $data['nome_ente'] ?? '',
            'codice_fiscale' => $data['codice_fiscale'] ?? '',
            'partita_iva' => $data['partita_iva'] ?? '',
            'numero_runts' => $data['numero_runts'] ?? '',
            'indirizzo' => $data['indirizzo'] ?? '',
            'email' => $data['email'] ?? '',
            'telefono' => $data['telefono'] ?? '',
            'logo_url' => $data['logo_url'] ?? '',
            'updated_at' => current_time('mysql'),
        ];

        if (! $existing) {
            $payload['created_at'] = current_time('mysql');
            return (bool) $wpdb->insert(
                $this->table,
                $payload,
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }

        return false !== $wpdb->update(
            $this->table,
            $payload,
            ['id' => (int) $existing['id']],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
    }
}
