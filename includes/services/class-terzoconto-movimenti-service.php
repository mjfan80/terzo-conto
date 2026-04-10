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

    public function create(array $data): array {
        $validation = $this->validate($data);

        if (! $validation['success']) {
            return $validation;
        }

        $created = $this->movimenti->create($data);
        if (! $created) {
            $error = $this->movimenti->get_last_error();

            return [
                'success' => false,
                'data' => null,
                'error' => $error !== '' ? $error : __('Errore creazione movimento', 'terzo-conto'),
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'error' => '',
        ];
    }

    public function update(int $id, array $data): array {
        if ($id <= 0) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('ID non valido', 'terzo-conto'),
            ];
        }

        $validation = $this->validate($data, $id);

        if (! $validation['success']) {
            return $validation;
        }

        $updated = $this->movimenti->update($id, $data);
        if (! $updated) {
            $error = $this->movimenti->get_last_error();

            return [
                'success' => false,
                'data' => null,
                'error' => $error !== '' ? $error : __('Errore aggiornamento movimento', 'terzo-conto'),
            ];
        }

        return [
            'success' => true,
            'data' => $data,
            'error' => '',
        ];
    }

    private function validate(array $data, int $movimento_id = 0): array {
        if (($data['data_movimento'] ?? '') === '' || ! $this->is_valid_date((string) $data['data_movimento'])) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('Inserisci una data movimento valida.', 'terzo-conto'),
            ];
        }

        if ((float) ($data['importo'] ?? 0) <= 0) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('Inserisci un importo maggiore di zero.', 'terzo-conto'),
            ];
        }

        if ((int) ($data['categoria_associazione_id'] ?? 0) <= 0) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('Seleziona una categoria valida.', 'terzo-conto'),
            ];
        }

        if ((int) ($data['conto_id'] ?? 0) <= 0) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('Seleziona un conto valido.', 'terzo-conto'),
            ];
        }

        $raccolta_id = (int) ($data['raccolta_fondi_id'] ?? 0);
        if ($raccolta_id > 0 && ! $this->raccolte->is_open($raccolta_id)) {
            return [
                'success' => false,
                'data' => null,
                'error' => __('La raccolta fondi è chiusa.', 'terzo-conto'),
            ];
        }

        if ($movimento_id > 0) {
            $current = $this->movimenti->find_by_id($movimento_id);
            if (! is_array($current)) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => __('Movimento non trovato.', 'terzo-conto'),
                ];
            }

            $current_year = (int) ($current['anno'] ?? 0);
            $new_year = (int) gmdate('Y', strtotime((string) $data['data_movimento']));

            if ($current_year > 0 && $new_year !== $current_year) {
                return [
                    'success' => false,
                    'data' => null,
                    'error' => __("Non è possibile modificare l'anno di un movimento. Eliminare il movimento e crearne uno nuovo.", 'terzo-conto'),
                ];
            }
        }

        return [
            'success' => true,
            'data' => $data,
            'error' => '',
        ];
    }

    private function is_valid_date(string $value): bool {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date instanceof DateTime && $date->format('Y-m-d') === $value;
    }
}
