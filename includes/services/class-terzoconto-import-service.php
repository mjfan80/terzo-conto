<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Import_Service {
    public function parse_csv(string $file_path, string $provider = 'generico'): array {
        $rows = [];
        if (! file_exists($file_path)) {
            return $rows;
        }

        if (($handle = fopen($file_path, 'r')) === false) {
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
            $rows[] = $this->normalize_row($row, $provider);
        }

        fclose($handle);
        return $rows;
    }

    public function detect_duplicates(array $rows, array $existing_movements): array {
        $dupes = [];
        foreach ($rows as $idx => $row) {
            foreach ($existing_movements as $movement) {
                if ($movement['data_movimento'] === $row['data_movimento'] && (float) $movement['importo'] === (float) $row['importo'] && $movement['descrizione'] === $row['descrizione']) {
                    $dupes[] = $idx;
                    break;
                }
            }
        }
        return $dupes;
    }

    private function normalize_row(array $row, string $provider): array {
        if ($provider === 'paypal') {
            return [
                'data_movimento' => $row['Date'] ?? '',
                'importo' => $row['Net'] ?? 0,
                'descrizione' => $row['Name'] ?? '',
            ];
        }

        if ($provider === 'satispay') {
            return [
                'data_movimento' => $row['date'] ?? '',
                'importo' => $row['amount'] ?? 0,
                'descrizione' => $row['description'] ?? '',
            ];
        }

        return [
            'data_movimento' => $row['data'] ?? $row['data_movimento'] ?? '',
            'importo' => $row['importo'] ?? 0,
            'descrizione' => $row['descrizione'] ?? '',
        ];
    }
}
