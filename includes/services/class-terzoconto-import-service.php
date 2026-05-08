<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Import_Service {
    public function parse_csv(string $file_path, string $provider = 'generico'): array {
        $provider = sanitize_key($provider);

        if ($provider !== 'generico') {
            return $this->parse_provider_csv($file_path, $provider);
        }

        return $this->parse_generic_csv($file_path);
    }

    public function detect_duplicates(array $rows, array $existing_movements): array {
        $dupes = [];

        foreach ($rows as $idx => $row) {
            if (! is_array($row)) {
                continue;
            }

            if (! empty($row['errors'])) {
                continue;
            }

            foreach ($existing_movements as $movement) {
                if (! is_array($movement)) {
                    continue;
                }

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
        return array_values(array_filter($rows, static function ($row): bool {
            return is_array($row) && empty($row['errors']);
        }));
    }

    private function parse_generic_csv(string $file_path): array {
        $rows = [];
    
        $normalized_path = wp_normalize_path($file_path);
        if (validate_file($normalized_path) !== 0 || ! file_exists($normalized_path)) {
            return $rows;
        }
    
        $lines = file($normalized_path, FILE_IGNORE_NEW_LINES);
    
        if ($lines === false || count($lines) === 0) {
            return $rows;
        }
    
        $header = str_getcsv(array_shift($lines), ';');
    
        if (! is_array($header)) {
            return $rows;
        }
    
        $normalized_header = array_map([$this, 'normalize_header'], $header);
        $has_tipo = in_array('tipo', $normalized_header, true);
    
        foreach ($lines as $line) {
            $data = str_getcsv($line, ';');
    
            if ($this->is_empty_csv_row($data)) {
                continue;
            }
    
            $row_data = array_slice(
                array_pad($data, count($normalized_header), ''),
                0,
                count($normalized_header)
            );
    
            $row = array_combine($normalized_header, $row_data);
            if (! is_array($row)) {
                $row = [];
            }
    
            $rows[] = $this->build_generic_preview_row(
                $row,
                $has_tipo,
                count($rows) + 1,
                count($data)
            );
        }
    
        return $rows;
    }

    private function parse_provider_csv(string $file_path, string $provider): array {
        $rows = [];
    
        $normalized_path = wp_normalize_path($file_path);
        if (validate_file($normalized_path) !== 0 || ! file_exists($normalized_path)) {
            return $rows;
        }
    
        $lines = file($normalized_path, FILE_IGNORE_NEW_LINES);
    
        if ($lines === false || count($lines) === 0) {
            return $rows;
        }
    
        $header = str_getcsv(array_shift($lines), ',');
    
        if (! is_array($header)) {
            return $rows;
        }
    
        foreach ($lines as $line) {
            $data = str_getcsv($line, ',');
    
            $row_data = array_slice(
                array_pad($data, count($header), ''),
                0,
                count($header)
            );
    
            $row = array_combine($header, $row_data);
            if (! is_array($row)) {
                continue;
            }
    
            $normalized = $this->normalize_row($row, $provider);
    
            $raw_importo = (float) ($normalized['importo'] ?? 0);
            $tipo = (string) ($normalized['tipo'] ?? '');
    
            if ($tipo === '') {
                $tipo = $raw_importo < 0 ? 'uscita' : 'entrata';
            }
    
            $importo = abs($raw_importo);
    
            $rows[] = [
                'row_number' => count($rows) + 1,
                'source_format' => 'provider',
                'data_movimento' => (string) ($normalized['data_movimento'] ?? ''),
                'importo' => $importo,
                'tipo' => $tipo,
                'descrizione' => (string) ($normalized['descrizione'] ?? ''),
                'errors' => [],
                'raw' => $row,
            ];
        }
    
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
                __('Numero colonne non valido. Attese %d colonne.', 'terzoconto-rendiconto-ets'),
                $expected_columns
            );
        }

        $data_value = $columns['data'] ?? $columns['data_movimento'] ?? '';
        $importo_value = $columns['importo'] ?? '';
        $descrizione_value = $columns['descrizione'] ?? '';
        $tipo_value = $has_tipo ? ($columns['tipo'] ?? '') : '';

        $parsed_date = $this->parse_date_value($data_value);
        if ($parsed_date === null) {
            $errors[] = __('Data non valida. Usa YYYY-MM-DD o DD/MM/YYYY.', 'terzoconto-rendiconto-ets');
        }

        $parsed_amount = $this->parse_amount_value($importo_value);
        if ($parsed_amount === null) {
            $errors[] = __('Importo non numerico.', 'terzoconto-rendiconto-ets');
        }

        if ($descrizione_value === '') {
            $errors[] = __('Descrizione obbligatoria.', 'terzoconto-rendiconto-ets');
        }

        $tipo = '';
        if ($has_tipo) {
            $tipo_normalized = strtoupper($tipo_value);
            if (! in_array($tipo_normalized, ['E', 'U'], true)) {
                $errors[] = __('Tipo non valido. Usa E o U.', 'terzoconto-rendiconto-ets');
            } else {
                $tipo = $tipo_normalized === 'E' ? 'entrata' : 'uscita';
            }

            if ($parsed_amount !== null && $parsed_amount < 0) {
                $errors[] = __('Nel formato con colonna tipo gli importi devono essere positivi.', 'terzoconto-rendiconto-ets');
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
        global $wp_filesystem;
    
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
    
        $normalized_path = wp_normalize_path($file_path);
    
        if (validate_file($normalized_path) !== 0) {
            return false;
        }
    
        if ( ! $wp_filesystem->exists($normalized_path) ) {
            return false;
        }
    
        $content = $wp_filesystem->get_contents($normalized_path);
    
        if ($content === false) {
            return false;
        }
    
        // Rimozione BOM UTF-8
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
    
        return $content;
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
    
        // Rileva negatività (prima di perdere info)
        $is_negative = false;
    
        // parentesi contabili (es: (123,45))
        if (preg_match('/^\(.*\)$/', $value)) {
            $is_negative = true;
            $value = trim($value, '()');
        }
    
        // unicode minus / dash
        if (preg_match('/[−–]/u', $value)) {
            $is_negative = true;
            $value = preg_replace('/[−–]/u', '-', $value);
        }
    
        // meno classico
        if (strpos($value, '-') !== false) {
            $is_negative = true;
        }
    
        // 2. pulizia
        $normalized = preg_replace('/[^0-9,.-]/', '', $value);
        if (! is_string($normalized) || $normalized === '') {
            return null;
        }
    
        // 3. normalizzazione separatori
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
    
        $amount = (float) $normalized;
    
        // 4. applica segno corretto
        return $is_negative ? -abs($amount) : $amount;
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
        $provider = sanitize_key($provider);

        if ($provider === 'paypal') {
            $net = $this->parse_amount_value((string) ($row['Net'] ?? ''));

            return [
                'data_movimento' => $this->parse_date_value((string) ($row['Date'] ?? '')) ?? '',
                'importo' => $net ?? 0.0,
                'tipo' => ($net ?? 0.0) < 0 ? 'uscita' : 'entrata',
                'descrizione' => (string) ($row['Name'] ?? ''),
            ];
        }

        if ($provider === 'satispay') {
            $amount = $this->parse_amount_value((string) ($row['amount'] ?? ''));

            return [
                'data_movimento' => $this->parse_date_value((string) ($row['date'] ?? '')) ?? '',
                'importo' => $amount ?? 0.0,
                'tipo' => ($amount ?? 0.0) < 0 ? 'uscita' : 'entrata',
                'descrizione' => (string) ($row['description'] ?? ''),
            ];
        }

        $tipo = sanitize_key((string) ($row['tipo'] ?? ''));
        if (! in_array($tipo, ['entrata', 'uscita'], true)) {
            $tipo = 'entrata';
        }

        $importo = $this->parse_amount_value((string) ($row['importo'] ?? ''));

        return [
            'data_movimento' => $this->parse_date_value((string) ($row['data'] ?? $row['data_movimento'] ?? '')) ?? '',
            'importo' => $importo ?? 0.0,
            'tipo' => $tipo,
            'descrizione' => (string) ($row['descrizione'] ?? ''),
        ];
    }
}
