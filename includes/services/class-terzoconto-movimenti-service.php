<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Movimenti_Service {
    public function __construct(
        private TerzoConto_Movimenti_Repository $movimenti,
        private TerzoConto_Raccolte_Repository $raccolte
    ) {
    }

    public function create(array $data) {
        $validation = $this->validate($data);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $created = $this->movimenti->create($data);
        if (! $created) {
            $error = $this->movimenti->get_last_error();

            return new WP_Error('movimento_create_failed', $error !== '' ? $error : __('Errore creazione movimento', 'terzo-conto'));
        }

        return $data;
    }

    public function update(int $id, array $data) {
        if ($id <= 0) {
            return new WP_Error('invalid_id', __('ID non valido', 'terzo-conto'));
        }

        $validation = $this->validate($data, $id);

        if (is_wp_error($validation)) {
            return $validation;
        }

        $updated = $this->movimenti->update($id, $data);
        if (! $updated) {
            $error = $this->movimenti->get_last_error();

            return new WP_Error('movimento_update_failed', $error !== '' ? $error : __('Errore aggiornamento movimento', 'terzo-conto'));
        }

        return $data;
    }

    private function validate(array $data, int $movimento_id = 0) {
        if (($data['data_movimento'] ?? '') === '' || ! $this->is_valid_date((string) $data['data_movimento'])) {
            return new WP_Error('invalid_data_movimento', __('Inserisci una data movimento valida.', 'terzo-conto'));
        }

        if ((float) ($data['importo'] ?? 0) <= 0) {
            return new WP_Error('invalid_importo', __('Inserisci un importo maggiore di zero.', 'terzo-conto'));
        }

        if ((int) ($data['categoria_associazione_id'] ?? 0) <= 0) {
            return new WP_Error('invalid_categoria_associazione', __('Seleziona una categoria valida.', 'terzo-conto'));
        }

        if ((int) ($data['conto_id'] ?? 0) <= 0) {
            return new WP_Error('invalid_conto', __('Seleziona un conto valido.', 'terzo-conto'));
        }

        $raccolta_id = (int) ($data['raccolta_fondi_id'] ?? 0);
        if ($raccolta_id > 0 && ! $this->raccolte->is_open($raccolta_id)) {
            return new WP_Error('raccolta_chiusa', __('La raccolta fondi è chiusa.', 'terzo-conto'));
        }

        if ($movimento_id > 0) {
            $current = $this->movimenti->find_by_id($movimento_id);
            if (! is_array($current)) {
                return new WP_Error('movimento_not_found', __('Movimento non trovato.', 'terzo-conto'));
            }

            $current_year = (int) ($current['anno'] ?? 0);
            $new_year = (int) gmdate('Y', strtotime((string) $data['data_movimento']));

            if ($current_year > 0 && $new_year !== $current_year) {
                return new WP_Error('invalid_anno_change', __("Non è possibile modificare l'anno di un movimento. Eliminare il movimento e crearne uno nuovo.", 'terzo-conto'));
            }
        }

        return true;
    }

    private function is_valid_date(string $value): bool {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date instanceof DateTime && $date->format('Y-m-d') === $value;
    }
}
