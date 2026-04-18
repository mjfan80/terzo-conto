<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Report_Service {

    /**
     * Genera la struttura dati completa per il Modello D (Anno Corrente vs Anno Precedente)
     */
    public function get_dati_modello_d(int $year): array {
        global $wpdb;

        $mov = $wpdb->prefix . 'terzoconto_movimenti';
        $cat_assoc = $wpdb->prefix . 'terzoconto_categorie_associazione';
        $cat_mod = $wpdb->prefix . 'terzoconto_categorie_modello_d';

        // Prendiamo TUTTE le voci del Modello D ufficiale (devono esserci tutte anche se a zero)
        $table = esc_sql($cat_mod);
        
        $voci = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY area ASC, tipo DESC, numero ASC",
            ARRAY_A
        );

        // Calcoliamo i totali per l'anno corrente
        $totali_corrente = $this->get_somme_per_anno($year);
        
        // Calcoliamo i totali per l'anno precedente
        $totali_precedente = $this->get_somme_per_anno($year - 1);

        $report = [];
        // Inizializziamo la struttura A, B, C, D, E
        foreach (['A', 'B', 'C', 'D', 'E'] as $area) {
            $report[$area] = ['E' => [], 'U' => []];
        }

        foreach ($voci as $voce) {
            $id_modello = (int) $voce['id'];
            $area = $voce['area'];
            $tipo = $voce['tipo']; // E o U
            
            $report[$area][$tipo][] = [
                'codice' => $voce['codice'],
                'nome'   => $voce['nome'],
                'numero' => $voce['numero'],
                'corrente'   => $totali_corrente[$id_modello] ?? 0.0,
                'precedente' => $totali_precedente[$id_modello] ?? 0.0,
            ];
        }

        return $report;
    }

    private function get_somme_per_anno(int $year): array {
        global $wpdb;
        $mov = $wpdb->prefix . 'terzoconto_movimenti';
        $cat_assoc = $wpdb->prefix . 'terzoconto_categorie_associazione';

        $mov_table = esc_sql($mov);
        $cat_assoc_table = esc_sql($cat_assoc);
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT ca.modello_d_id, SUM(m.importo) as totale
                FROM {$mov_table} m
                JOIN {$cat_assoc_table} ca ON m.categoria_associazione_id = ca.id
                WHERE m.anno = %d AND m.stato = 'attivo'
                GROUP BY ca.modello_d_id
                ",
                $year
            ),
            ARRAY_A
        );
        $somme = [];
        if ($results) {
            foreach ($results as $row) {
                $somme[(int)$row['modello_d_id']] = (float)$row['totale'];
            }
        }
        return $somme;
    }

    /**
     * Calcola i saldi dei conti (Cassa/Banca) al 31/12 dell'anno specificato
     */
    public function get_saldi_conti(int $year): array {
        global $wpdb;
        $mov = $wpdb->prefix . 'terzoconto_movimenti';
        $conti = $wpdb->prefix . 'terzoconto_conti';

        // Somma Entrate - Somma Uscite fino al 31/12 dell'anno
        return $wpdb->get_results(
            $wpdb->prepare(
                "
                SELECT c.nome, 
                       SUM(CASE WHEN m.tipo = 'entrata' THEN m.importo ELSE 0 END) - 
                       SUM(CASE WHEN m.tipo = 'uscita' THEN m.importo ELSE 0 END) as saldo
                FROM {$mov} m
                JOIN {$conti} c ON m.conto_id = c.id
                WHERE m.anno <= %d AND m.stato = 'attivo'
                GROUP BY c.id
                ORDER BY c.nome ASC
                ",
                $year
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Estrae i dati dettagliati per il report di una singola Raccolta Fondi
     */
    public function get_dati_raccolta(int $raccolta_id): array {
        global $wpdb;
        $mov = $wpdb->prefix . 'terzoconto_movimenti';
        $cat_assoc = $wpdb->prefix . 'terzoconto_categorie_associazione';

        // Entrate
        $entrate = $wpdb->get_results($wpdb->prepare("
            SELECT ca.nome as categoria, SUM(m.importo) as totale
            FROM {$mov} m JOIN {$cat_assoc} ca ON m.categoria_associazione_id = ca.id
            WHERE m.raccolta_fondi_id = %d AND m.tipo = 'entrata' AND m.stato = 'attivo'
            GROUP BY ca.id
        ", $raccolta_id), ARRAY_A) ?: [];

        // Uscite
        $uscite = $wpdb->get_results($wpdb->prepare("
            SELECT ca.nome as categoria, SUM(m.importo) as totale
            FROM {$mov} m JOIN {$cat_assoc} ca ON m.categoria_associazione_id = ca.id
            WHERE m.raccolta_fondi_id = %d AND m.tipo = 'uscita' AND m.stato = 'attivo'
            GROUP BY ca.id
        ", $raccolta_id), ARRAY_A) ?: [];

        $tot_entrate = array_sum(array_column($entrate, 'totale'));
        $tot_uscite = array_sum(array_column($uscite, 'totale'));

        return [
            'entrate' => $entrate,
            'uscite' => $uscite,
            'totale_entrate' => $tot_entrate,
            'totale_uscite' => $tot_uscite,
            'risultato' => $tot_entrate - $tot_uscite
        ];
    }

    public function export_csv_movimenti(array $movimenti): string {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['id', 'data_movimento', 'importo', 'tipo', 'descrizione', 'stato']);
        foreach ($movimenti as $row) {
            fputcsv($fh, [
                $row['id'] ?? '', $row['data_movimento'] ?? '', $row['importo'] ?? '',
                $row['tipo'] ?? '', $row['descrizione'] ?? '', $row['stato'] ?? '',
            ]);
        }
        rewind($fh);
        return (string) stream_get_contents($fh);
    }
}
