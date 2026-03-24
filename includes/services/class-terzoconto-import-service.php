<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Import_Service {
    public function parse_csv(string $file_path, string $provider = 'generico'): array {
        if ($provider !== 'generico') {
            return $this->parse_provider_csv($file_path, $provider);
        }

        return $this->parse_generic_csv($file_path);
    }

    public function detect_duplicates(array $rows, array $existing_movements): array {
        $dupes = [];

        foreach ($rows as $idx => $row) {
            if (! empty($row['errors'])) {
                continue;
            }

            foreach ($existing_movements as $movement) {
                if (
                    (string) ($movement['data_movimento'] ?? '') === (string) ($row['data_movimento'] ?? '')
                    && (float) ($movement['importo'] ?? 0) === (float) ($row['importo'] ?? 0)
                    && (string) ($movement['descrizione'] ?? '') === (string) ($row['descrizione'] ?? '')
                    && (string) ($movement['tipo'] ?? '') === (string) ($row['tipo'] ?? '')
                ) {
                    $dupes[] = $idx;
                    break;
                }
            }
        }

        return $dupes;
    }

    public function get_valid_rows(array $rows): array {
        return array_values(array_filter($rows, static function (array $row): bool {
            return empty($row['errors']);
        }));
    }

    private function parse_generic_csv(string $file_path): array {
        $rows = [];
        $handle = $this->open_csv_file($file_path);

        if (! is_resource($handle)) {
            return $rows;
        }

        $header = fgetcsv($handle, 0, ';');
        if (! is_array($header)) {
            fclose($handle);
            return $rows;
        }

        $normalized_header = array_map([$this, 'normalize_header'], $header);
        $has_tipo = in_array('tipo', $normalized_header, true);

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($this->is_empty_csv_row($data)) {
                continue;
            }

            $row_data = array_slice(array_pad($data, count($normalized_header), ''), 0, count($normalized_header));
            $row = array_combine($normalized_header, $row_data);
            if (! is_array($row)) {
                $row = [];
            }

            $rows[] = $this->build_generic_preview_row($row, $has_tipo, count($rows) + 1, count($data));
        }

        fclose($handle);

        return $rows;
    }

    private function parse_provider_csv(string $file_path, string $provider): array {
        $rows = [];
        $handle = $this->open_csv_file($file_path);
    
        if (! is_resource($handle)) {
            return $rows;
        }
    
        $header = fgetcsv($handle, 0, ',');
        if (! $header) {
            fclose($handle);
            return $rows;
        }
    
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            $row = array_combine($header, $data);
            if (! is_array($row)) {
                continue;
            }
    
            $normalized = $this->normalize_row($row, $provider);
    
            // 👉 NORMALIZZAZIONE IMPORTO + TIPO
            $raw_importo = (float) ($normalized['importo'] ?? 0);
    
            // tipo coerente con segno SOLO se non già impostato correttamente
            $tipo = (string) ($normalized['tipo'] ?? '');
    
            if ($tipo === '') {
                $tipo = $raw_importo < 0 ? 'uscita' : 'entrata';
            }
    
            // 👉 IMPORTO SEMPRE POSITIVO
            $importo = abs($raw_importo);
    
            $rows[] = [
                'row_number' => count($rows) + 1,
                'source_format' => 'provider',
                'data_movimento' => (string) ($normalized['data_movimento'] ?? ''),
                'importo' => $importo,
                'tipo' => $tipo,
                'descrizione' => (string) ($normalized['descrizione'] ?? ''),
                'errors' => [],
            ];
        }
    
        fclose($handle);
    
        return $rows;
    }

    private function build_generic_preview_row(array $columns, bool $has_tipo, int $row_number, ?int $actual_columns = null): array {
        $errors = [];
        $expected_columns = $has_tipo ? 4 : 3;
        $columns = array_map(static fn($value): string => trim((string) $value), $columns);
        $actual_columns = $actual_columns ?? count($columns);

        if ($actual_columns !== $expected_columns) {
            $errors[] = sprintf(
                /* translators: %d: expected columns count */
                __('Numero colonne non valido. Attese %d colonne.', 'terzo-conto'),
                $expected_columns
            );
        }

        $data_value = $columns['data'] ?? '';
        $importo_value = $columns['importo'] ?? '';
        $descrizione_value = $columns['descrizione'] ?? '';
        $tipo_value = $has_tipo ? ($columns['tipo'] ?? '') : '';

        $parsed_date = $this->parse_date_value($data_value);
        if ($parsed_date === null) {
            $errors[] = __('Data non valida. Usa YYYY-MM-DD o DD/MM/YYYY.', 'terzo-conto');
        }

        $parsed_amount = $this->parse_amount_value($importo_value);
        if ($parsed_amount === null) {
            $errors[] = __('Importo non numerico.', 'terzo-conto');
        }

        if ($descrizione_value === '') {
            $errors[] = __('Descrizione obbligatoria.', 'terzo-conto');
        }

        $tipo = '';
        if ($has_tipo) {
            $tipo_normalized = strtoupper($tipo_value);
            if (! in_array($tipo_normalized, ['E', 'U'], true)) {
                $errors[] = __('Tipo non valido. Usa E o U.', 'terzo-conto');
            } else {
                $tipo = $tipo_normalized === 'E' ? 'entrata' : 'uscita';
            }

            if ($parsed_amount !== null && $parsed_amount < 0) {
                $errors[] = __('Nel formato con colonna tipo gli importi devono essere positivi.', 'terzo-conto');
            }
        } elseif ($parsed_amount !== null) {
            $tipo = $parsed_amount < 0 ? 'uscita' : 'entrata';
            $parsed_amount = abs($parsed_amount);
        }

        if ($has_tipo && $parsed_amount !== null) {
            $parsed_amount = abs($parsed_amount);
        }

        return [
            'row_number' => $row_number,
            'source_format' => $has_tipo ? '4-colonne' : '3-colonne',
            'data_movimento' => $parsed_date ?? '',
            'importo' => $parsed_amount ?? 0.0,
            'tipo' => $tipo,
            'descrizione' => $descrizione_value,
            'errors' => array_values(array_unique($errors)),
            'raw' => [
                'data' => $data_value,
                'importo' => $importo_value,
                'descrizione' => $descrizione_value,
                'tipo' => $tipo_value,
            ],
        ];
    }

    private function open_csv_file(string $file_path) {
        if (! file_exists($file_path)) {
            return false;
        }

        $handle = fopen($file_path, 'r');
        if ($handle === false) {
            return false;
        }

        $first_bytes = fread($handle, 3);
        if ($first_bytes !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        return $handle;
    }

    private function normalize_header(string $header): string {
        $header = strtolower(trim($header));
        $header = str_replace(["\xEF\xBB\xBF", ' '], ['', '_'], $header);

        return $header;
    }

    private function is_empty_csv_row(array $row): bool {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function parse_amount_value(string $value): ?float {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/[^0-9,.-]/', '', $value);
        if (! is_string($normalized) || $normalized == '') {
            return null;
        }

        if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } else {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function parse_date_value(string $value): ?string {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat('!' . $format, $value);
            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalize_row(array $row, string $provider): array {
        if ($provider === 'paypal') {
            return [
                'data_movimento' => $row['Date'] ?? '',
                'importo' => $row['Net'] ?? 0,
                'tipo' => ((float) ($row['Net'] ?? 0)) < 0 ? 'uscita' : 'entrata',
                'descrizione' => $row['Name'] ?? '',
            ];
        }

        if ($provider === 'satispay') {
            return [
                'data_movimento' => $row['date'] ?? '',
                'importo' => $row['amount'] ?? 0,
                'tipo' => ((float) ($row['amount'] ?? 0)) < 0 ? 'uscita' : 'entrata',
                'descrizione' => $row['description'] ?? '',
            ];
        }

        return [
            'data_movimento' => $row['data'] ?? $row['data_movimento'] ?? '',
            'importo' => $row['importo'] ?? 0,
            'tipo' => $row['tipo'] ?? 'entrata',
            'descrizione' => $row['descrizione'] ?? '',
        ];
    }
}
