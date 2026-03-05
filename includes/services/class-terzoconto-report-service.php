<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Report_Service {
    public function get_bilancio_annuale(int $year): array {
        global $wpdb;

        $movimenti = $wpdb->prefix . 'terzoconto_movimenti';
        $categorie_assoc = $wpdb->prefix . 'terzoconto_categorie_associazione';
        $categorie_modeld = $wpdb->prefix . 'terzoconto_categorie_modello_d';

        $sql = $wpdb->prepare(
            "SELECT md.codice, md.nome, md.tipo, SUM(m.importo) AS totale
            FROM {$movimenti} m
            INNER JOIN {$categorie_assoc} ca ON ca.id = m.categoria_associazione_id
            INNER JOIN {$categorie_modeld} md ON md.id = ca.modello_d_id
            WHERE m.anno = %d AND m.stato = 'attivo'
            GROUP BY md.id
            ORDER BY md.tipo, md.codice",
            $year
        );

        return $wpdb->get_results($sql, ARRAY_A) ?: [];
    }

    public function export_csv_movimenti(array $movimenti): string {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['id', 'data_movimento', 'importo', 'tipo', 'descrizione', 'stato']);

        foreach ($movimenti as $row) {
            fputcsv($fh, [
                $row['id'] ?? '',
                $row['data_movimento'] ?? '',
                $row['importo'] ?? '',
                $row['tipo'] ?? '',
                $row['descrizione'] ?? '',
                $row['stato'] ?? '',
            ]);
        }

        rewind($fh);
        return (string) stream_get_contents($fh);
    }
}
