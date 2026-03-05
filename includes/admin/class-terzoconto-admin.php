<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-movimenti-list-table.php';

class TerzoConto_Admin {
    public function __construct(
        private TerzoConto_Movimenti_Repository $movimenti,
        private TerzoConto_Categorie_Repository $categorie,
        private TerzoConto_Conti_Repository $conti,
        private TerzoConto_Raccolte_Repository $raccolte,
        private TerzoConto_Import_Service $import_service,
        private TerzoConto_Report_Service $report_service
    ) {
    }

    public function hooks(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'handle_post_actions']);
    }

    public function register_menu(): void {
        $cap = 'manage_options';
        add_menu_page('TerzoConto', 'TerzoConto', $cap, 'terzoconto', [$this, 'render_movimenti'], 'dashicons-ledger', 30);
        add_submenu_page('terzoconto', __('Movimenti', 'terzo-conto'), __('Movimenti', 'terzo-conto'), $cap, 'terzoconto', [$this, 'render_movimenti']);
        add_submenu_page('terzoconto', __('Categorie', 'terzo-conto'), __('Categorie', 'terzo-conto'), $cap, 'terzoconto-categorie', [$this, 'render_categorie']);
        add_submenu_page('terzoconto', __('Conti', 'terzo-conto'), __('Conti', 'terzo-conto'), $cap, 'terzoconto-conti', [$this, 'render_conti']);
        add_submenu_page('terzoconto', __('Raccolte fondi', 'terzo-conto'), __('Raccolte fondi', 'terzo-conto'), $cap, 'terzoconto-raccolte', [$this, 'render_raccolte']);
        add_submenu_page('terzoconto', __('Import', 'terzo-conto'), __('Import', 'terzo-conto'), $cap, 'terzoconto-import', [$this, 'render_import']);
        add_submenu_page('terzoconto', __('Report', 'terzo-conto'), __('Report', 'terzo-conto'), $cap, 'terzoconto-report', [$this, 'render_report']);
    }

    public function handle_post_actions(): void {
        if (! current_user_can('manage_options') || ! isset($_POST['terzoconto_action'])) {
            return;
        }

        check_admin_referer('terzoconto_action_nonce');
        $action = sanitize_text_field(wp_unslash($_POST['terzoconto_action']));

        switch ($action) {
            case 'add_movimento':
                $raccolta_id = absint($_POST['raccolta_fondi_id'] ?? 0);
                if ($raccolta_id > 0 && ! $this->raccolte->is_open($raccolta_id)) {
                    add_settings_error('terzoconto', 'raccolta_chiusa', __('La raccolta fondi è chiusa.', 'terzo-conto'), 'error');
                    break;
                }
                $this->movimenti->create([
                    'data_movimento' => sanitize_text_field(wp_unslash($_POST['data_movimento'] ?? '')),
                    'importo' => (float) str_replace(',', '.', (string) ($_POST['importo'] ?? 0)),
                    'tipo' => sanitize_text_field(wp_unslash($_POST['tipo'] ?? 'entrata')),
                    'categoria_associazione_id' => absint($_POST['categoria_associazione_id'] ?? 0),
                    'conto_id' => absint($_POST['conto_id'] ?? 0),
                    'raccolta_fondi_id' => $raccolta_id,
                    'descrizione' => sanitize_text_field(wp_unslash($_POST['descrizione'] ?? '')),
                ]);
                break;
            case 'add_categoria_associazione':
                $this->categorie->create_associazione(
                    sanitize_text_field(wp_unslash($_POST['nome'] ?? '')),
                    absint($_POST['modello_d_id'] ?? 0),
                    sanitize_text_field(wp_unslash($_POST['descrizione'] ?? ''))
                );
                break;
            case 'add_conto':
                $this->conti->create(
                    sanitize_text_field(wp_unslash($_POST['nome'] ?? '')),
                    sanitize_text_field(wp_unslash($_POST['descrizione'] ?? ''))
                );
                break;
            case 'add_raccolta':
                $this->raccolte->create([
                    'nome' => sanitize_text_field(wp_unslash($_POST['nome'] ?? '')),
                    'descrizione' => sanitize_text_field(wp_unslash($_POST['descrizione'] ?? '')),
                    'data_inizio' => sanitize_text_field(wp_unslash($_POST['data_inizio'] ?? '')),
                    'data_fine' => sanitize_text_field(wp_unslash($_POST['data_fine'] ?? '')),
                    'stato' => sanitize_text_field(wp_unslash($_POST['stato'] ?? 'aperta')),
                ]);
                break;
            case 'import_preview':
                $this->handle_import_preview();
                break;
            case 'export_movimenti_csv':
                $this->download_csv();
                break;
        }
    }

    public function render_movimenti(): void {
        $movimenti = $this->movimenti->get_all();
        $categorie = $this->categorie->get_associazione();
        $conti = $this->conti->get_all();
        $raccolte = $this->raccolte->get_aperte();

        echo '<div class="wrap"><h1>TerzoConto - ' . esc_html__('Movimenti', 'terzo-conto') . '</h1>';
        settings_errors('terzoconto');
        $this->render_movimento_form($categorie, $conti, $raccolte);
        $table = new TerzoConto_Movimenti_List_Table($movimenti);
        $table->prepare_items();
        $table->display();
        echo '</div>';
    }

    public function render_categorie(): void {
        $modello_d = $this->categorie->get_modello_d();
        $categorie = $this->categorie->get_associazione();

        echo '<div class="wrap"><h1>' . esc_html__('Categorie', 'terzo-conto') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="add_categoria_associazione" />';
        echo '<p><input type="text" name="nome" placeholder="' . esc_attr__('Nome categoria', 'terzo-conto') . '" required /></p>';
        echo '<p><select name="modello_d_id" required>';
        foreach ($modello_d as $md) {
            echo '<option value="' . esc_attr((string) $md['id']) . '">' . esc_html($md['codice'] . ' - ' . $md['nome']) . '</option>';
        }
        echo '</select></p>';
        echo '<p><input type="text" name="descrizione" placeholder="' . esc_attr__('Descrizione', 'terzo-conto') . '" /></p>';
        submit_button(__('Aggiungi categoria', 'terzo-conto'));
        echo '</form><hr />';

        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Nome', 'terzo-conto') . '</th><th>' . esc_html__('Modello D', 'terzo-conto') . '</th></tr></thead><tbody>';
        foreach ($categorie as $cat) {
            echo '<tr><td>' . esc_html($cat['nome']) . '</td><td>' . esc_html($cat['modello_d_codice'] . ' - ' . $cat['modello_d_nome']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_conti(): void {
        $conti = $this->conti->get_all();
        echo '<div class="wrap"><h1>' . esc_html__('Conti', 'terzo-conto') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="add_conto" />';
        echo '<p><input type="text" name="nome" required placeholder="' . esc_attr__('Nome conto', 'terzo-conto') . '" /></p>';
        echo '<p><input type="text" name="descrizione" placeholder="' . esc_attr__('Descrizione', 'terzo-conto') . '" /></p>';
        submit_button(__('Aggiungi conto', 'terzo-conto'));
        echo '</form><hr /><ul>';
        foreach ($conti as $conto) {
            echo '<li>' . esc_html($conto['nome']) . '</li>';
        }
        echo '</ul></div>';
    }

    public function render_raccolte(): void {
        $raccolte = $this->raccolte->get_all();
        echo '<div class="wrap"><h1>' . esc_html__('Raccolte fondi', 'terzo-conto') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="add_raccolta" />';
        echo '<p><input type="text" name="nome" required placeholder="' . esc_attr__('Nome', 'terzo-conto') . '" /></p>';
        echo '<p><input type="text" name="descrizione" placeholder="' . esc_attr__('Descrizione', 'terzo-conto') . '" /></p>';
        echo '<p><input type="date" name="data_inizio" required /> <input type="date" name="data_fine" /></p>';
        echo '<p><select name="stato"><option value="aperta">' . esc_html__('Aperta', 'terzo-conto') . '</option><option value="chiusa">' . esc_html__('Chiusa', 'terzo-conto') . '</option></select></p>';
        submit_button(__('Aggiungi raccolta', 'terzo-conto'));
        echo '</form><hr /><ul>';
        foreach ($raccolte as $raccolta) {
            echo '<li>' . esc_html($raccolta['nome'] . ' (' . $raccolta['stato'] . ')') . '</li>';
        }
        echo '</ul></div>';
    }

    public function render_import(): void {
        echo '<div class="wrap"><h1>' . esc_html__('Import CSV', 'terzo-conto') . '</h1>';
        echo '<form method="post" enctype="multipart/form-data">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="import_preview" />';
        echo '<p><select name="provider"><option value="generico">CSV generico</option><option value="paypal">CSV PayPal</option><option value="satispay">CSV Satispay</option></select></p>';
        echo '<p><input type="file" name="csv_file" accept=".csv" required /></p>';
        submit_button(__('Carica e anteprima', 'terzo-conto'));
        echo '</form>';

        $preview = get_transient('terzoconto_import_preview_' . get_current_user_id());
        if (is_array($preview) && ! empty($preview['rows'])) {
            echo '<h2>' . esc_html__('Anteprima', 'terzo-conto') . '</h2><table class="widefat"><thead><tr><th>Data</th><th>Importo</th><th>Descrizione</th><th>Possibile duplicato</th></tr></thead><tbody>';
            foreach ($preview['rows'] as $i => $row) {
                $is_dupe = in_array($i, $preview['duplicates'], true);
                echo '<tr><td>' . esc_html($row['data_movimento']) . '</td><td>' . esc_html((string) $row['importo']) . '</td><td>' . esc_html($row['descrizione']) . '</td><td>' . ($is_dupe ? '⚠️' : '') . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
    }

    public function render_report(): void {
        $year = isset($_GET['year']) ? absint($_GET['year']) : (int) gmdate('Y');
        $report = $this->report_service->get_bilancio_annuale($year);
        echo '<div class="wrap"><h1>' . esc_html__('Report', 'terzo-conto') . '</h1>';
        echo '<form method="get"><input type="hidden" name="page" value="terzoconto-report" />';
        echo '<input type="number" name="year" value="' . esc_attr((string) $year) . '" min="2000" max="2100" />';
        submit_button(__('Carica report', 'terzo-conto'), '', '', false);
        echo '</form>';

        echo '<h2>' . esc_html__('Bilancio annuale aggregato per Modello D', 'terzo-conto') . '</h2>';
        echo '<table class="widefat"><thead><tr><th>Codice</th><th>Voce</th><th>Tipo</th><th>Totale</th></tr></thead><tbody>';
        foreach ($report as $row) {
            echo '<tr><td>' . esc_html($row['codice']) . '</td><td>' . esc_html($row['nome']) . '</td><td>' . esc_html($row['tipo']) . '</td><td>' . esc_html(number_format((float) $row['totale'], 2, ',', '.')) . '</td></tr>';
        }
        echo '</tbody></table>';

        echo '<form method="post">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="export_movimenti_csv" />';
        submit_button(__('Export CSV backup movimenti', 'terzo-conto'));
        echo '</form>';
        echo '</div>';
    }

    private function render_movimento_form(array $categorie, array $conti, array $raccolte): void {
        echo '<form method="post">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="add_movimento" />';
        echo '<p><input type="date" name="data_movimento" required /> <input type="text" name="importo" required placeholder="0,00" />';
        echo '<select name="tipo"><option value="entrata">' . esc_html__('Entrata', 'terzo-conto') . '</option><option value="uscita">' . esc_html__('Uscita', 'terzo-conto') . '</option></select></p>';
        echo '<p><select name="categoria_associazione_id" required>';
        foreach ($categorie as $cat) {
            echo '<option value="' . esc_attr((string) $cat['id']) . '">' . esc_html($cat['nome']) . '</option>';
        }
        echo '</select>';
        echo '<select name="conto_id" required>';
        foreach ($conti as $conto) {
            echo '<option value="' . esc_attr((string) $conto['id']) . '">' . esc_html($conto['nome']) . '</option>';
        }
        echo '</select></p>';
        echo '<p><select name="raccolta_fondi_id"><option value="0">' . esc_html__('Nessuna raccolta', 'terzo-conto') . '</option>';
        foreach ($raccolte as $raccolta) {
            echo '<option value="' . esc_attr((string) $raccolta['id']) . '">' . esc_html($raccolta['nome']) . '</option>';
        }
        echo '</select></p>';
        echo '<p><input type="text" name="descrizione" placeholder="' . esc_attr__('Descrizione', 'terzo-conto') . '" /></p>';
        submit_button(__('Aggiungi movimento', 'terzo-conto'));
        echo '</form><hr />';
    }

    private function handle_import_preview(): void {
        if (! isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
            return;
        }

        $provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? 'generico'));
        $rows = $this->import_service->parse_csv($_FILES['csv_file']['tmp_name'], $provider);
        $duplicates = $this->import_service->detect_duplicates($rows, $this->movimenti->get_all());

        set_transient('terzoconto_import_preview_' . get_current_user_id(), [
            'rows' => $rows,
            'duplicates' => $duplicates,
        ], MINUTE_IN_SECONDS * 30);
    }

    private function download_csv(): void {
        $movimenti = $this->movimenti->get_all();
        $csv = $this->report_service->export_csv_movimenti($movimenti);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=terzoconto-movimenti.csv');
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
