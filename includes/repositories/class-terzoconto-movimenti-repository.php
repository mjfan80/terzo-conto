<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Movimenti_Repository {
    private string $table;
    private string $last_error = '';

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'terzoconto_movimenti';
    }

    public function get_all(): array {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE %d = %d ORDER BY data_movimento DESC, id DESC", 1, 1),
            ARRAY_A
        ) ?: [];
    }

    public function find_by_id(int $id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }


    public function get_last_error(): string {
        return $this->last_error;
    }

    public function create(array $data): bool {
        global $wpdb;
        $this->last_error = '';
        $year = (int) gmdate('Y', strtotime($data['data_movimento']));
        $progressivo = $this->next_progressivo($year);
        $now = current_time('mysql');

        $created = (bool) $wpdb->insert($this->table, [
            'progressivo_annuale' => $progressivo,
            'anno' => $year,
            'data_movimento' => $data['data_movimento'],
            'importo' => $data['importo'],
            'tipo' => $data['tipo'],
            'categoria_associazione_id' => $data['categoria_associazione_id'],
            'conto_id' => $data['conto_id'],
            'raccolta_fondi_id' => $data['raccolta_fondi_id'] ?: null,
            'anagrafica_id' => $data['anagrafica_id'] ?: null,
            'descrizione' => $data['descrizione'],
            'user_id' => get_current_user_id(),
            'stato' => $data['stato'] ?? 'attivo',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%d', '%d', '%s', '%f', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s']);

        if (! $created && $wpdb->last_error !== '') {
            $this->last_error = $wpdb->last_error;
        }

        return $created;
    }

    public function update(int $id, array $data): bool {
        global $wpdb;

        $this->last_error = '';

        $current = $this->find_by_id($id);
        if (! is_array($current)) {
            return false;
        }

        $current_year = (int) $current['anno'];
        $new_year = (int) gmdate('Y', strtotime((string) $data['data_movimento']));

        if ($new_year !== $current_year) {
            $this->last_error = __("Non è possibile modificare l'anno di un movimento. Eliminare il movimento e crearne uno nuovo.", 'terzo-conto');
            return false;
        }

        $updated = false !== $wpdb->update(
            $this->table,
            [
                'data_movimento' => $data['data_movimento'],
                'importo' => $data['importo'],
                'tipo' => $data['tipo'],
                'categoria_associazione_id' => $data['categoria_associazione_id'],
                'conto_id' => $data['conto_id'],
                'raccolta_fondi_id' => $data['raccolta_fondi_id'] ?: null,
                'anagrafica_id' => $data['anagrafica_id'] ?: null,
                'descrizione' => $data['descrizione'],
				'stato' => $data['stato'] ?? 'attivo',
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%f', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s'],
            ['%d']
        );

        if (! $updated && $this->last_error === '' && $wpdb->last_error !== '') {
            $this->last_error = $wpdb->last_error;
        }

        return $updated;
    }

    private function next_progressivo(int $year): int {
        global $wpdb;
        $max = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(progressivo_annuale) FROM {$this->table} WHERE anno = %d", $year));
        return $max + 1;
    }

    public function mark_annullato(int $id): bool {
        global $wpdb;
        return false !== $wpdb->update(
            $this->table,
            ['stato' => 'annullato', 'updated_at' => current_time('mysql')],
            ['id' => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
