<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-movimenti-list-table.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-admin-security.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-admin-validator.php';
require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-admin-conti-page.php';

class TerzoConto_Admin {
    private ?array $submitted_movimento = null;
    private ?array $submitted_conto = null;
    private ?array $submitted_raccolta = null;

    private TerzoConto_Admin_Security $security;
    private TerzoConto_Admin_Validator $validator;
    private TerzoConto_Admin_Conti_Page $conti_page;

    public function __construct(
        private TerzoConto_Movimenti_Repository $movimenti,
        private TerzoConto_Movimenti_Service $movimenti_service,
        private TerzoConto_Categorie_Repository $categorie,
        private TerzoConto_Conti_Repository $conti,
        private TerzoConto_Raccolte_Repository $raccolte,
        private TerzoConto_Anagrafiche_Repository $anagrafiche,
        private TerzoConto_Import_Service $import_service,
        private TerzoConto_Report_Service $report_service,
        ?TerzoConto_Admin_Security $security = null,
        ?TerzoConto_Admin_Validator $validator = null,
        ?TerzoConto_Admin_Conti_Page $conti_page = null
    ) {
        $this->security = $security ?? new TerzoConto_Admin_Security();
        $this->validator = $validator ?? new TerzoConto_Admin_Validator();
        $this->conti_page = $conti_page ?? new TerzoConto_Admin_Conti_Page();
    }

    public function hooks(): void {
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'handle_post_actions']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

    public function register_menu(): void {
        $cap = 'manage_options';
        add_menu_page(
            esc_html__('TerzoConto', 'terzoconto-rendiconto-ets'),
            esc_html__('TerzoConto', 'terzoconto-rendiconto-ets'),
            $cap,
            'terzoconto',
            [$this, 'render_movimenti'],
            'dashicons-ledger',
            30
        );
        add_submenu_page('terzoconto', __('Movimenti', 'terzoconto-rendiconto-ets'), __('Movimenti', 'terzoconto-rendiconto-ets'), $cap, 'terzoconto', [$this, 'render_movimenti']);
        add_submenu_page('terzoconto', __('Categorie', 'terzoconto-rendiconto-ets'), __('Categorie', 'terzoconto-rendiconto-ets'), $cap, 'terzoconto-categorie', [$this, 'render_categorie']);
        add_submenu_page('terzoconto', __('Conti', 'terzoconto-rendiconto-ets'), __('Conti', 'terzoconto-rendiconto-ets'), $cap, 'terzoconto-conti', [$this, 'render_conti']);
        add_submenu_page('terzoconto', __('Raccolte fondi', 'terzoconto-rendiconto-ets'), __('Raccolte fondi', 'terzoconto-rendiconto-ets'), $cap, 'terzoconto-raccolte', [$this, 'render_raccolte']);
        add_submenu_page('terzoconto', __('Import', 'terzoconto-rendiconto-ets'), __('Import', 'terzoconto-rendiconto-ets'), $cap, 'terzoconto-import', [$this, 'render_import']);
        add_submenu_page('terzoconto', __('Report', 'terzoconto-rendiconto-ets'), __('Report', 'terzoconto-rendiconto-ets'), $cap, 'terzoconto-report', [$this, 'render_report']);
    }

    public function handle_post_actions(): void {
        if (! current_user_can('manage_options')) {
            return;
        }
		
        // Recuperiamo l'azione sia da POST che da GET (per far funzionare i link come "Annulla")
        $action = '';
        if (isset($_POST['terzoconto_action'])) {
            $action = sanitize_text_field(wp_unslash($_POST['terzoconto_action']));
        } elseif (isset($_GET['terzoconto_action'])) {
            $action = sanitize_text_field(wp_unslash($_GET['terzoconto_action']));
        }

		if ($action === '') {
			return;
		}

		if (! $this->security->assert_manage_capability()) {
		    return;
		}

        // Verifica di sicurezza differenziata in base all'azione
        if ($action === 'bulk_update_movimenti') {
            check_admin_referer('terzoconto_action_nonce', 'tc_bulk_nonce');
        } elseif ($action === 'annulla_movimento') {
            if (isset($_GET['terzoconto_action']) && ! isset($_POST['terzoconto_action'])) {
                $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
                if (! wp_verify_nonce($nonce, 'terzoconto_action_nonce')) {
                    return;
                }
            } else {
                check_admin_referer('terzoconto_action_nonce');
            }
		} else {
            // Azioni standard POST (inserimento movimento, creazione conto, ecc...)
            check_admin_referer('terzoconto_action_nonce');
        }

        switch ($action) {
            case 'add_conto':
                $this->handle_create_conto();
                break;

            case 'update_conto':
                $this->handle_update_conto();
                break;

            case 'delete_conto':
                $this->handle_delete_conto();
                break;

            case 'add_raccolta':
                $this->handle_create_raccolta();
                break;

            case 'update_raccolta':
                $this->handle_update_raccolta();
                break;

            case 'delete_raccolta':
                $this->handle_delete_raccolta();
                break;

			case 'import_preview':
				$this->handle_import_preview();
				break;

			case 'import_commit':
				$this->handle_import_commit();
				break;

            case 'export_movimenti_csv':
                $this->download_csv();
                break;
				
			case 'add_movimento':
				$this->handle_create_movimento();
				break;

			case 'update_movimento':
				$this->handle_update_movimento();
				break;

			case 'annulla_movimento':
				$this->handle_annulla_movimento();
				break;
				
			case 'bulk_update_movimenti':
				$this->handle_bulk_update_movimenti();
				break;
		}
	}

    public function render_movimenti(): void {
        if (! $this->security->assert_manage_capability()) {
            wp_die(esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets'));
        }

        $stato_filter = $this->get_movimento_stato_filter();
        $movimenti = []; // query fatta dentro la list-table
        $categorie = $this->categorie->get_associazione();
        $conti = $this->conti->get_all();
        $raccolte = $this->raccolte->get_aperte();
        $anagrafiche = $this->anagrafiche->search('');

        $edit_id = absint(wp_unslash($_GET['edit_movimento_id'] ?? 0));
        $movimento = $edit_id > 0 ? $this->movimenti->find_by_id($edit_id) : null;
        if (is_array($this->submitted_movimento)) {
            $movimento = $this->submitted_movimento;
        }

        if (is_array($movimento) && ! empty($movimento['raccolta_fondi_id'])) {
            $selected_raccolta_id = (int) $movimento['raccolta_fondi_id'];
            $found = false;
            foreach ($raccolte as $raccolta) {
                if ((int) $raccolta['id'] === $selected_raccolta_id) {
                    $found = true;
                    break;
                }
            }

            if (! $found) {
                $selected_raccolta = $this->raccolte->find_by_id($selected_raccolta_id);
                if (is_array($selected_raccolta)) {
                    $raccolte[] = $selected_raccolta;
                }
            }
        }

        if ($stato_filter !== '') {
            $movimenti = array_values(array_filter($movimenti, static function (array $row) use ($stato_filter): bool {
                return (string) ($row['stato'] ?? '') === $stato_filter;
            }));
        }

        echo '<div class="wrap"><h1>' . esc_html__('TerzoConto - Movimenti', 'terzoconto-rendiconto-ets') . '</h1>';
        $this->render_movimenti_notice(); 
		settings_errors('terzoconto');
        $this->render_movimento_form($categorie, $conti, $raccolte, $anagrafiche, $movimento);
        $this->render_movimenti_filters($stato_filter);
        $table = new TerzoConto_Movimenti_List_Table($movimenti);
        $table->prepare_items();
		echo '<form method="post">';
		wp_nonce_field('terzoconto_action_nonce', 'tc_bulk_nonce');

		echo '<input type="hidden" name="terzoconto_action" value="bulk_update_movimenti" />';

		echo '<div style="margin:10px 0;padding:10px;background:#fff;border:1px solid #ccd0d4;">';

		echo '<strong>' . esc_html__('Modifica massiva', 'terzoconto-rendiconto-ets') . '</strong><br /><br />';

		echo '<select name="bulk_categoria_id">';
		echo '<option value="">' . esc_html__('-- Categoria (opzionale) --', 'terzoconto-rendiconto-ets') . '</option>';
		foreach ($categorie as $cat) {
			echo '<option value="'.esc_attr($cat['id']).'">'.esc_html($cat['nome']).'</option>';
		}
		echo '</select> ';

		echo '<select name="bulk_conto_id">';
		echo '<option value="">' . esc_html__('-- Conto (opzionale) --', 'terzoconto-rendiconto-ets') . '</option>';
		foreach ($conti as $conto) {
			echo '<option value="'.esc_attr($conto['id']).'">'.esc_html($conto['nome']).'</option>';
		}
		echo '</select> ';

		echo '<select name="bulk_raccolta_id">';
		echo '<option value="">' . esc_html__('-- Raccolta (opzionale) --', 'terzoconto-rendiconto-ets') . '</option>';
		foreach ($raccolte as $raccolta) {
			echo '<option value="'.esc_attr($raccolta['id']).'">'.esc_html($raccolta['nome']).'</option>';
		}
		echo '</select> ';

		echo '<select name="bulk_anagrafica_id">';
		echo '<option value="">' . esc_html__('-- Anagrafica (opzionale) --', 'terzoconto-rendiconto-ets') . '</option>';
		foreach ($anagrafiche as $a) {
			$label = $this->format_anagrafica_label($a);
			echo '<option value="'.esc_attr($a['id']).'">'.esc_html($label).'</option>';
		}
		echo '</select> ';

		submit_button(__('Applica ai selezionati', 'terzoconto-rendiconto-ets'), 'primary', '', false);

		echo '</div>';

		$table->display();

		echo '</form>';
		$this->render_support_box();
        echo '</div>';
    }

    public function enqueue_assets(string $hook): void {
		$plugin_pages = [
			'toplevel_page_terzoconto',
			'terzoconto_page_terzoconto-categorie',
			'terzoconto_page_terzoconto-conti',
			'terzoconto_page_terzoconto-raccolte',
			'terzoconto_page_terzoconto-import',
			'terzoconto_page_terzoconto-report',
		];

		if (! in_array($hook, $plugin_pages, true)) {
			return;
		}

		$select2_version = '4.1.0-rc.0';
		$select2_base_url = TERZOCONTO_PLUGIN_URL . 'assets/vendor/select2/';

		// SELECT2
		wp_enqueue_style(
			'select2',
			$select2_base_url . 'select2.min.css',
			[],
			$select2_version
		);

		wp_enqueue_style(
			'terzoconto-admin',
			TERZOCONTO_PLUGIN_URL . 'assets/css/admin.css',
			[],
			TERZOCONTO_VERSION
		);

		wp_enqueue_script(
			'select2',
			$select2_base_url . 'select2.min.js',
			['jquery'],
			$select2_version,
			true
		);

		// INIT SELECT2
		wp_add_inline_script('select2', "
			jQuery(document).ready(function($) {
				$('#terzoconto-anagrafica-select').select2({
					width: '100%',
					placeholder: 'Seleziona o cerca anagrafica',
					allowClear: true,
				    minimumInputLength: 1
				});
			});
		");
		
		wp_add_inline_style('select2', "
		
			.terzoconto-support-box {
				background: #fff;
				border: 1px solid #ccd0d4;
				padding: 16px;
				margin-top: 30px;
				max-width: 500px;
			}

			.terzoconto-support-box h2 {
				margin-top: 0;
			}

			.terzoconto-support-box .button {
				margin-top: 5px;
			}

			/* ====== TABELLA MOVIMENTI ====== */

			.wp-list-table th.column-id,
			.wp-list-table td.column-id {
				width: 70px; 
			}

			/* Stile per le icone Modifica/Annulla */
			.wp-list-table .row-actions span.dashicons {
				font-size: 18px;
				width: 18px;
				height: 18px;
				margin-top: 2px;
			}
			.wp-list-table .row-actions .edit a { color: #2271b1; } /* Matita blu */
			.wp-list-table .row-actions .annulla a { color: #d63638; } /* Cestino rosso */

            /* --- AGGIUNTO IL CSS PER LA COLONNA STATO QUI --- */
			.wp-list-table th.column-stato,
			.wp-list-table td.column-stato {
				width: 75px;
                text-align: center;
			}

			.wp-list-table th.column-data_movimento,
			.wp-list-table td.column-data_movimento {
				width: 110px;
			}

			.wp-list-table th.column-progressivo_annuale,
			.wp-list-table td.column-progressivo_annuale {
				width: 60px;
			}

			.wp-list-table th.column-tipo,
			.wp-list-table td.column-tipo {
				width: 80px;
			}

			.wp-list-table th.column-importo,
			.wp-list-table td.column-importo {
				width: 100px;
				text-align: right;
			}

			.wp-list-table th.column-conto,
			.wp-list-table td.column-conto {
				width: 140px;
			}

			.wp-list-table th.column-categoria,
			.wp-list-table td.column-categoria {
				width: 160px;
			}

			.wp-list-table th.column-raccolta,
			.wp-list-table td.column-raccolta {
				width: 160px;
			}

			.wp-list-table th.column-anagrafica,
			.wp-list-table td.column-anagrafica {
				width: 180px;
			}

			.wp-list-table th.column-descrizione,
			.wp-list-table td.column-descrizione {
				width: auto;
			}

		");
		
		wp_add_inline_style('select2', "

			/* ===== FORM MOVIMENTO GRID ===== */

			.terzoconto-movimento-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 12px;
				max-width: 1000px;
				margin-bottom: 12px;
			}

			.terzoconto-movimento-grid p {
				margin: 0;
			}

			.terzoconto-movimento-grid input,
			.terzoconto-movimento-grid select {
				width: 100%;
			}

			.select2-container {
				width: 100% !important;
			}

		");
	}
	
	private function render_movimenti_notice(): void {
        // Notifiche standard creazione/modifica/annullamento
        $status = sanitize_text_field(wp_unslash($_GET['tc_movimento_status'] ?? ''));
        if ($status === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Movimento creato con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Movimento aggiornato con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'annullato') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Movimento annullato con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        }

        // Notifiche Modifica Massiva
        $bulk_status = sanitize_text_field(wp_unslash($_GET['tc_bulk'] ?? ''));
        if ($bulk_status === 'done') {
            $count = absint(wp_unslash($_GET['updated'] ?? 0));
			/* translators: %d = number of movements updated */										  
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('Modifica massiva applicata con successo a %d movimenti.', 'terzoconto-rendiconto-ets'), $count)) . '</p></div>';
        } elseif ($bulk_status === 'no_ids') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Nessun movimento selezionato. Seleziona almeno una riga usando le caselle di controllo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($bulk_status === 'no_fields') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Nessun campo selezionato per la modifica massiva. Scegli almeno un valore dai menu a tendina.', 'terzoconto-rendiconto-ets') . '</p></div>';
        }
    }

    public function render_categorie(): void {
        if (! $this->security->assert_manage_capability()) {
            wp_die(esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets'));
        }

        $categorie = $this->categorie->get_associazione();

        echo '<div class="wrap"><h1>' . esc_html__('Categorie', 'terzoconto-rendiconto-ets') . '</h1>';
        echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field('terzoconto_action_nonce');
		echo '<input type="hidden" name="terzoconto_action" value="import_preview" />';
		echo '<p><select name="provider">
		<option value="generico">' . esc_html__('CSV generico', 'terzoconto-rendiconto-ets') . '</option>
		<option value="paypal">' . esc_html__('CSV PayPal', 'terzoconto-rendiconto-ets') . '</option>
		<option value="satispay">' . esc_html__('CSV Satispay', 'terzoconto-rendiconto-ets') . '</option>
		</select></p>';
		echo '<p><input type="file" name="csv_file" accept=".csv" required /></p>';
		submit_button(__('Carica e anteprima', 'terzoconto-rendiconto-ets'));
		echo '</form>';

        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Nome', 'terzoconto-rendiconto-ets') . '</th><th>' . esc_html__('Modello D', 'terzoconto-rendiconto-ets') . '</th></tr></thead><tbody>';
        foreach ($categorie as $cat) {
            echo '<tr><td>' . esc_html($cat['nome']) . '</td><td>' . esc_html($cat['modello_d_codice'] . ' - ' . $cat['modello_d_nome']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_conti(): void {
        if (! $this->security->assert_manage_capability()) {
            wp_die(esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets'));
        }

        $edit_id = absint(wp_unslash($_GET['edit_conto_id'] ?? 0));
        $conto = $edit_id > 0 ? $this->conti->find_by_id($edit_id) : null;
        if (is_array($this->submitted_conto)) {
            $conto = $this->submitted_conto;
        }

        $this->conti_page->render($this, [
            'is_edit' => is_array($conto),
            'conto' => $conto,
            'conti' => $this->conti->get_all(),
        ]);
    }

    public function render_raccolte(): void {
        if (! $this->security->assert_manage_capability()) {
            wp_die(esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets'));
        }

        $edit_id = absint(wp_unslash($_GET['edit_raccolta_id'] ?? 0));
        $raccolta = $edit_id > 0 ? $this->raccolte->find_by_id($edit_id) : null;
        if (is_array($this->submitted_raccolta)) {
            $raccolta = $this->submitted_raccolta;
        }

        $is_edit = is_array($raccolta) && !empty($raccolta['id']);
        $raccolte = $this->raccolte->get_all();

        echo '<div class="wrap"><h1>' . esc_html__('Raccolte fondi', 'terzoconto-rendiconto-ets') . '</h1>';
        $this->render_raccolte_notice();
        settings_errors('terzoconto_raccolte');

        echo '<h2>' . esc_html($is_edit ? __('Modifica raccolta fondi', 'terzoconto-rendiconto-ets') : __('Nuova raccolta fondi', 'terzoconto-rendiconto-ets')) . '</h2>';
        echo '<p class="terzoconto-raccolte-help">' . esc_html__('Compila i dati della raccolta fondi occasionale. La relazione illustrativa verrà utilizzata per i report ufficiali RUNTS.', 'terzoconto-rendiconto-ets') . '</p>';
        echo '<form method="post">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="' . esc_attr($is_edit ? 'update_raccolta' : 'add_raccolta') . '" />';
        if ($is_edit) {
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $raccolta['id']) . '" />';
        }
        echo '<div class="terzoconto-raccolte-form-grid">';
        echo '<p><input type="text" name="nome" required placeholder="' . esc_attr__('Nome', 'terzoconto-rendiconto-ets') . '" value="' . esc_attr((string) ($raccolta['nome'] ?? '')) . '" /></p>';
        echo '<p><input type="text" name="descrizione" placeholder="' . esc_attr__('Descrizione', 'terzoconto-rendiconto-ets') . '" value="' . esc_attr((string) ($raccolta['descrizione'] ?? '')) . '" /></p>';
        echo '<p><input type="date" name="data_inizio" required value="' . esc_attr((string) ($raccolta['data_inizio'] ?? '')) . '" /></p>';
        echo '<p><input type="date" name="data_fine" value="' . esc_attr((string) ($raccolta['data_fine'] ?? '')) . '" /></p>';
        echo '<p><select name="stato">';
        echo '<option value="aperta" ' . selected((string) ($raccolta['stato'] ?? 'aperta'), 'aperta', false) . '>' . esc_html__('Aperta', 'terzoconto-rendiconto-ets') . '</option>';
        echo '<option value="chiusa" ' . selected((string) ($raccolta['stato'] ?? ''), 'chiusa', false) . '>' . esc_html__('Chiusa', 'terzoconto-rendiconto-ets') . '</option>';
        echo '</select></p>';
        echo '<p style="grid-column:1 / -1;"><label for="tc-relazione-illustrativa">' . esc_html__('Relazione illustrativa (per report RUNTS)', 'terzoconto-rendiconto-ets') . '</label><br />';
        echo '<textarea id="tc-relazione-illustrativa" name="relazione_illustrativa" rows="8" placeholder="' . esc_attr__('Descrizione narrativa della raccolta fondi', 'terzoconto-rendiconto-ets') . '">' . esc_textarea((string) ($raccolta['relazione_illustrativa'] ?? '')) . '</textarea>';
        echo '<small style="display:block;margin-top:4px;color:#646970;">' . esc_html__('Inserire una descrizione della raccolta fondi (contesto, modalità, finalità e utilizzo dei fondi). Questo testo sarà utilizzato nei report ufficiali.', 'terzoconto-rendiconto-ets') . '</small></p>';
        echo '</div>';
        submit_button($is_edit ? __('Aggiorna raccolta', 'terzoconto-rendiconto-ets') : __('Aggiungi raccolta', 'terzoconto-rendiconto-ets'));
        echo '</form><hr />';

        echo '<h2>' . esc_html__('Elenco raccolte fondi', 'terzoconto-rendiconto-ets') . '</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Nome', 'terzoconto-rendiconto-ets') . '</th>';
        echo '<th>' . esc_html__('Stato', 'terzoconto-rendiconto-ets') . '</th>';
        echo '<th>' . esc_html__('Periodo', 'terzoconto-rendiconto-ets') . '</th>';
        echo '<th>' . esc_html__('Azioni', 'terzoconto-rendiconto-ets') . '</th>';
        echo '</tr></thead><tbody>';

        if ($raccolte === []) {
            echo '<tr><td colspan="4">' . esc_html__('Nessuna raccolta presente.', 'terzoconto-rendiconto-ets') . '</td></tr>';
        }

        foreach ($raccolte as $raccolta) {
            $edit_url = add_query_arg(['page' => 'terzoconto-raccolte', 'edit_raccolta_id' => (int) $raccolta['id']], admin_url('admin.php'));
            $is_open = (string) ($raccolta['stato'] ?? '') === 'aperta';
            $status_label = $is_open ? __('Aperta', 'terzoconto-rendiconto-ets') : __('Chiusa', 'terzoconto-rendiconto-ets');
            $status_class = $is_open ? 'is-open' : 'is-closed';
            $cannot_delete = ! $this->raccolte->can_delete((int) $raccolta['id']);
            $data_inizio = (string) ($raccolta['data_inizio'] ?? '');
            $data_fine = (string) ($raccolta['data_fine'] ?? '');
            $periodo = $data_inizio !== '' ? $data_inizio : '—';
            if ($data_fine !== '') {
                $periodo .= ' → ' . $data_fine;
            }

            echo '<tr>';
            echo '<td><a href="' . esc_url($edit_url) . '">' . esc_html((string) $raccolta['nome']) . '</a></td>';
            echo '<td><span class="terzoconto-raccolte-status-badge ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span></td>';
            echo '<td>' . esc_html($periodo) . '</td>';
            echo '<td>';
            echo '<a class="button button-secondary" href="' . esc_url($edit_url) . '">' . esc_html__('Modifica', 'terzoconto-rendiconto-ets') . '</a> ';
            echo '<form method="post" style="display:inline-block;margin-left:6px;">';
            wp_nonce_field('terzoconto_action_nonce');
            echo '<input type="hidden" name="terzoconto_action" value="delete_raccolta" />';
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $raccolta['id']) . '" />';
            if ($cannot_delete) {
                echo '<button type="submit" class="button button-link-delete" disabled="disabled" title="' . esc_attr__('La raccolta è associata a movimenti e non può essere eliminata.', 'terzoconto-rendiconto-ets') . '">' . esc_html__('Elimina', 'terzoconto-rendiconto-ets') . '</button>';
            } else {
                echo '<button type="submit" class="button button-link-delete" onclick="return confirm(\'' . esc_js(__('Vuoi davvero eliminare questa raccolta?', 'terzoconto-rendiconto-ets')) . '\');">' . esc_html__('Elimina', 'terzoconto-rendiconto-ets') . '</button>';
            }
            echo '</form>';
            if ($cannot_delete) {
                echo '<br /><small>' . esc_html__('Non eliminabile: raccolta associata a movimenti.', 'terzoconto-rendiconto-ets') . '</small>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function render_import(): void {
        if (! $this->security->assert_manage_capability()) {
            wp_die(esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets'));
        }


		$preview = get_transient($this->get_import_preview_transient_key());

		$categorie = $this->categorie->get_associazione();
		$conti = $this->conti->get_all();

		echo '<div class="wrap"><h1>' . esc_html__('Import CSV', 'terzoconto-rendiconto-ets') . '</h1>';

		settings_errors('terzoconto');
		
		echo '<div style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:12px 0;max-width:900px;">';

		echo '<strong>' . esc_html__('Formato CSV richiesto', 'terzoconto-rendiconto-ets') . '</strong><br /><br />';

		echo '<ul style="margin-left:18px;">';
		echo '<li>' . esc_html__('Separatore:', 'terzoconto-rendiconto-ets') . ' <strong>' . esc_html__('punto e virgola ( ; )', 'terzoconto-rendiconto-ets') . '</strong></li>';
		echo '<li>' . esc_html__('Encoding: UTF-8', 'terzoconto-rendiconto-ets') . '</li>';
		echo '<li>' . esc_html__('Numero colonne: 3 oppure 4', 'terzoconto-rendiconto-ets') . '</li>';
		echo '</ul>';

		echo '<strong>' . esc_html__('Formato a 3 colonne:', 'terzoconto-rendiconto-ets') . '</strong><br />';
		echo '<code>data;importo;descrizione</code><br />';
		echo '<small>' . esc_html__('Il tipo (entrata/uscita) viene dedotto automaticamente dal segno dell\'importo', 'terzoconto-rendiconto-ets') . '</small><br /><br />';

		echo '<strong>' . esc_html__('Formato a 4 colonne:', 'terzoconto-rendiconto-ets') . '</strong><br />';
		echo '<code>data;importo;descrizione;tipo</code><br />';
		echo '<small>' . esc_html__('Tipo: E = entrata, U = uscita (in questo formato gli importi devono essere positivi)', 'terzoconto-rendiconto-ets') . '</small><br /><br />';

		echo '<strong>' . esc_html__('Formato data:', 'terzoconto-rendiconto-ets') . '</strong> ' . esc_html__('YYYY-MM-DD oppure DD/MM/YYYY', 'terzoconto-rendiconto-ets') . '<br />';
		echo '<strong>' . esc_html__('Importo:', 'terzoconto-rendiconto-ets') . '</strong> ' . esc_html__('numero (usa il punto come separatore decimale, es. 123.45)', 'terzoconto-rendiconto-ets') . '<br /><br />';

		echo '<strong>' . esc_html__('Esempio valido:', 'terzoconto-rendiconto-ets') . '</strong><br />';
		echo '<pre style="background:#f6f7f7;padding:8px;">';
		echo "data;importo;descrizione;tipo\n";
		echo "2025-10-31;50.00;Donazione evento;E\n";
		echo "2025-10-31;20.00;Acquisto materiali;U";
		echo '</pre>';

		echo '</div>';
		

		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field('terzoconto_action_nonce');
		echo '<input type="hidden" name="terzoconto_action" value="import_preview" />';
		echo '<p><select name="provider">
			<option value="generico">' . esc_html__('CSV generico', 'terzoconto-rendiconto-ets') . '</option>
			<option value="paypal">' . esc_html__('CSV PayPal', 'terzoconto-rendiconto-ets') . '</option>
			<option value="satispay">' . esc_html__('CSV Satispay', 'terzoconto-rendiconto-ets') . '</option>
		</select></p>';
		echo '<p><input type="file" name="csv_file" accept=".csv" required /></p>';
		submit_button(__('Carica e anteprima', 'terzoconto-rendiconto-ets'));
		echo '</form>';

		if (is_array($preview) && isset($preview['rows'])) {

			$rows = $preview['rows'];
			$valid_rows = $preview['valid_rows'];
			$duplicates = $preview['duplicates'];

			echo '<h2>' . esc_html__('Anteprima', 'terzoconto-rendiconto-ets') . '</h2>';
			/* translators: 1: number of valid rows, 2: total rows */												
			echo '<p>' . esc_html(sprintf(__('Righe valide: %1$d su %2$d', 'terzoconto-rendiconto-ets'), count($valid_rows), count($rows))) . '</p>';

			echo '<form method="post">';
			wp_nonce_field('terzoconto_action_nonce');
			echo '<input type="hidden" name="terzoconto_action" value="import_commit" />';

			echo '<table class="widefat"><thead>
			<tr>
			<th>#</th>
			<th>Data</th>
			<th>Importo</th>
			<th>Tipo</th>
			<th>Descrizione</th>
			<th>Categoria</th>
			<th>Conto</th>
			<th>Esito</th>
			</tr>
			</thead><tbody>';

			foreach ($rows as $i => $row) {

				$is_dupe = in_array($i, $duplicates, true);
				$errors = $row['errors'] ?? [];

				echo '<tr>';

				echo '<td>' . esc_html((string) ($i + 1)) . '</td>';

				echo '<td><input type="date" name="rows[' . esc_attr($i) . '][data_movimento]" value="' . esc_attr($row['data_movimento']) . '"></td>';

				echo '<td><input type="text" name="rows[' . esc_attr($i) . '][importo]" value="' . esc_attr($row['importo']) . '"></td>';

				echo '<td>
					<select name="rows[' . esc_attr($i) . '][tipo]">
						<option value="entrata" ' . selected($row['tipo'], 'entrata', false) . '>' . esc_html__('Entrata', 'terzoconto-rendiconto-ets') . '</option>
						<option value="uscita" ' . selected($row['tipo'], 'uscita', false) . '>' . esc_html__('Uscita', 'terzoconto-rendiconto-ets') . '</option>
					</select>
				</td>';

				echo '<td><input type="text" name="rows[' . esc_attr($i) . '][descrizione]" value="' . esc_attr($row['descrizione']) . '" style="width:100%"></td>';

				echo '<td>' . wp_kses_post(
					$this->render_categoria_select_html(
						$categorie,
						'rows[' . esc_attr($i) . '][categoria_id]',
						0,
						true
					)
				) . '</td>';
				
				echo '<td><select name="rows['.  esc_attr($i) .'][conto_id]" required>';
				echo '<option value="">' . esc_html__('-- conto --', 'terzoconto-rendiconto-ets') . '</option>';

				foreach ($conti as $conto) {
					echo '<option value="'.esc_attr($conto['id']).'">'.esc_html($conto['nome']).'</option>';
				}

				echo '</select></td>';

				$status = [];
				if ($errors) $status[] = implode(' ', $errors);
				if ($is_dupe) $status[] = __('Duplicato', 'terzoconto-rendiconto-ets');
				if (! $status) $status[] = __('OK', 'terzoconto-rendiconto-ets');

				echo '<td>' . esc_html(implode(' | ', $status)) . '</td>';

				echo '</tr>';
			}

			echo '</tbody></table>';

			submit_button(__('Importa tutto', 'terzoconto-rendiconto-ets'));

			echo '</form>';

			echo '<h3>' . esc_html__('Importa righe valide', 'terzoconto-rendiconto-ets') . '</h3>';

			echo '<form method="post">';
			wp_nonce_field('terzoconto_action_nonce');
			echo '<input type="hidden" name="terzoconto_action" value="import_commit" />';
			
			echo '<p><select name="categoria_associazione_id" required>';
			echo '<option value="0">' . esc_html__('Seleziona categoria', 'terzoconto-rendiconto-ets') . '</option>';
			foreach ($categorie as $categoria) {
			    echo '<option value="' . esc_attr($categoria['id']) . '">' . esc_html($categoria['nome']) . '</option>';
			}
			echo '</select></p>';
			
			echo '<p><select name="conto_id" required>';
			echo '<option value="0">' . esc_html__('Seleziona conto', 'terzoconto-rendiconto-ets') . '</option>';
			foreach ($conti as $conto) {
			    echo '<option value="' . esc_attr($conto['id']) . '">' . esc_html($conto['nome']) . '</option>';
			}
			echo '</select></p>';
			
			submit_button(__('Importa righe valide', 'terzoconto-rendiconto-ets'));
			
			echo '</form>';
		}

		echo '</div>';
	}

    public function render_report(): void {
        if (! $this->security->assert_manage_capability()) {
            wp_die(esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets'));
        }

        // Recuperiamo le impostazioni dell'ente per le intestazioni
        $settings_repo = new TerzoConto_Settings_Repository();
        $settings = $settings_repo->get() ?: [];
        $nome_ente = $settings['nome_ente'] ?? 'Nome Ente Non Impostato';
        $cf_ente = $settings['codice_fiscale'] ?? 'CF Non Impostato';

        $tab = sanitize_text_field(wp_unslash($_GET['tab'] ?? 'modello_d'));
        $year = isset($_GET['year']) ? absint(wp_unslash($_GET['year'])) : (int) gmdate('Y');

        echo '<div class="wrap tc-report-wrap">';
        
        // CONTROLLI (NON STAMPABILI)
        echo '<div class="tc-no-print" style="margin-bottom: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px;">';
        echo '<h1>' . esc_html__('Report e Stampe', 'terzoconto-rendiconto-ets') . ' <button class="button button-primary tc-print-btn" onclick="window.print();"><span class="dashicons dashicons-printer" style="margin-top:4px;"></span> ' . esc_html__('Stampa PDF', 'terzoconto-rendiconto-ets') . '</button></h1>';
        
        echo '<h2 class="nav-tab-wrapper">';
        echo '<a href="' . esc_url(add_query_arg(['page' => 'terzoconto-report', 'tab' => 'modello_d'], admin_url('admin.php'))) . '" class="nav-tab ' . esc_attr($tab === 'modello_d' ? 'nav-tab-active' : '') . '">' . esc_html__('Modello D (Rendiconto per Cassa)', 'terzoconto-rendiconto-ets') . '</a>';
        echo '<a href="' . esc_url(add_query_arg(['page' => 'terzoconto-report', 'tab' => 'raccolte'], admin_url('admin.php'))) . '" class="nav-tab ' . esc_attr($tab === 'raccolte' ? 'nav-tab-active' : '') . '">' . esc_html__('Report Raccolte Fondi', 'terzoconto-rendiconto-ets') . '</a>';
        echo '</h2>';
        echo '</div>'; // Fine area no-print


        if ($tab === 'modello_d') {
            // === RENDER MODELLO D ===
            echo '<div class="tc-no-print" style="margin-bottom: 20px;">';
            echo '<form method="get" style="display:inline-block; margin-right: 20px;">
                    <input type="hidden" name="page" value="terzoconto-report" />
                    <input type="hidden" name="tab" value="modello_d" />
					<strong>' . esc_html__('Anno di riferimento:', 'terzoconto-rendiconto-ets') . '</strong>
					<input type="number" name="year" value="' . esc_attr((string) $year) . '" min="2000" max="2100" style="width: 80px;" />';
			
            echo wp_kses_post(get_submit_button(__('Aggiorna', 'terzoconto-rendiconto-ets'), 'secondary', '', false));
			
			echo '</form>';
            // Form esportazione backup (lo teniamo qui)
            echo '<form method="post" style="display:inline-block;">';
            wp_nonce_field('terzoconto_action_nonce');
            echo '<input type="hidden" name="terzoconto_action" value="export_movimenti_csv" />';
            submit_button(__('Backup Movimenti CSV', 'terzoconto-rendiconto-ets'), 'secondary', '', false);
            echo '</form>';
            echo '</div>';

            $dati_modello = $this->report_service->get_dati_modello_d($year);
            $titoli_aree = [
                'A' => 'A) ATTIVITÀ DI INTERESSE GENERALE',
                'B' => 'B) ATTIVITÀ DIVERSE',
                'C' => 'C) ATTIVITÀ DI RACCOLTA FONDI',
                'D' => 'D) ATTIVITÀ FINANZIARIE E PATRIMONIALI',
                'E' => 'E) ATTIVITÀ DI SUPPORTO GENERALE'
            ];

            echo '<div class="tc-report-header">';
            echo '<h2>Ente del Terzo Settore - ' . esc_html($nome_ente) . '</h2>';
            echo '<h3>C.F. ' . esc_html($cf_ente) . '</h3>';
            echo '<p>Modello D - Rendiconto Per Cassa (Decreto Min. Lavoro 5 marzo 2020)<br>Anno ' . esc_html((string)$year) . '</p>';
            echo '</div>';

            echo '<table class="tc-modello-d">';
            echo '<thead>
                    <tr><th colspan="3" style="font-size:14px;">USCITE</th><th colspan="3" style="font-size:14px;">ENTRATE</th></tr>
                    <tr>
                        <th width="35%">Voce</th><th width="10%">' . esc_html((string)$year) . '</th><th width="10%">' . esc_html((string)($year-1)) . '</th>
                        <th width="35%">Voce</th><th width="10%">' . esc_html((string)$year) . '</th><th width="10%">' . esc_html((string)($year-1)) . '</th>
                    </tr>
                  </thead><tbody>';

            $gran_totale_uscite_corr = 0; $gran_totale_uscite_prec = 0;
            $gran_totale_entrate_corr = 0; $gran_totale_entrate_prec = 0;

            foreach (['A', 'B', 'C', 'D', 'E'] as $area) {
                echo '<tr><td colspan="6" class="section-title">' . esc_html($titoli_aree[$area]) . '</td></tr>';
                
                $uscite = $dati_modello[$area]['U'];
                $entrate = $dati_modello[$area]['E'];
                $max_rows = max(count($uscite), count($entrate));

                $tot_u_corr = 0; $tot_u_prec = 0;
                $tot_e_corr = 0; $tot_e_prec = 0;

                for ($i = 0; $i < $max_rows; $i++) {
                    echo '<tr>';
                    // USCITE
                    if (isset($uscite[$i])) {
                        $u = $uscite[$i];
                        echo '<td>' . esc_html((string) $u['numero']) . ') ' . esc_html($u['nome']) . '</td>';
                        echo '<td class="text-right">€ ' . esc_html(number_format((float) $u['corrente'], 2, ',', '.')) . '</td>';
                        echo '<td class="text-right">€ ' . esc_html(number_format((float) $u['precedente'], 2, ',', '.')) . '</td>';
                        $tot_u_corr += $u['corrente']; $tot_u_prec += $u['precedente'];
                    } else {
                        echo '<td></td><td></td><td></td>';
                    }

                    // ENTRATE
                    if (isset($entrate[$i])) {
                        $e = $entrate[$i];
                        echo '<td>' . esc_html((string) $e['numero']) . ') ' . esc_html($e['nome']) . '</td>';
                        echo '<td class="text-right">€ ' . esc_html(number_format((float) $e['corrente'], 2, ',', '.')) . '</td>';
                        echo '<td class="text-right">€ ' . esc_html(number_format((float) $e['precedente'], 2, ',', '.')) . '</td>';
                        $tot_e_corr += $e['corrente']; $tot_e_prec += $e['precedente'];
                    } else {
                        echo '<td></td><td></td><td></td>';
                    }
                    echo '</tr>';
                }

                // Totale Sezione
                $avanzo_corr = $tot_e_corr - $tot_u_corr;
                $avanzo_prec = $tot_e_prec - $tot_u_prec;
                echo '<tr class="totale-row">';
                echo '<td>TOTALE USCITE '. esc_html($area) .'</td><td class="text-right">€ ' . esc_html(number_format($tot_u_corr, 2, ',', '.')) . '</td><td class="text-right">€ ' . esc_html(number_format($tot_u_prec, 2, ',', '.')) . '</td>';
                echo '<td>TOTALE ENTRATE '. esc_html($area) .'</td><td class="text-right">€ ' . esc_html(number_format($tot_e_corr, 2, ',', '.')) . '</td><td class="text-right">€ ' . esc_html(number_format($tot_e_prec, 2, ',', '.')) . '</td>';
                echo '</tr>';
                echo '<tr class="totale-row"><td colspan="3"></td><td>Avanzo/Disavanzo (+/-)</td><td class="text-right">€ ' . esc_html(number_format($avanzo_corr, 2, ',', '.')) . '</td><td class="text-right">€ ' . esc_html(number_format($avanzo_prec, 2, ',', '.')) . '</td></tr>';

                $gran_totale_uscite_corr += $tot_u_corr; $gran_totale_uscite_prec += $tot_u_prec;
                $gran_totale_entrate_corr += $tot_e_corr; $gran_totale_entrate_prec += $tot_e_prec;
            }

            // TOTALE GESTIONE CORRENTE
            echo '<tr><td colspan="6" style="height:10px;"></td></tr>';
            echo '<tr class="totale-row" style="background:#e0e0e0; font-size: 14px;">';
            echo '<td>TOTALE GENERALE USCITE</td><td class="text-right">€ ' . esc_html(number_format($gran_totale_uscite_corr, 2, ',', '.')) . '</td><td class="text-right">€ ' . esc_html(number_format($gran_totale_uscite_prec, 2, ',', '.')) . '</td>';
            echo '<td>TOTALE GENERALE ENTRATE</td><td class="text-right">€ ' . esc_html(number_format($gran_totale_entrate_corr, 2, ',', '.')) . '</td><td class="text-right">€ ' . esc_html(number_format($gran_totale_entrate_prec, 2, ',', '.')) . '</td>';
            echo '</tr>';
            echo '<tr class="totale-row" style="background:#e0e0e0; font-size: 14px;"><td colspan="3"></td><td>RISULTATO D\'ESERCIZIO (+/-)</td><td class="text-right">€ ' . esc_html(number_format($gran_totale_entrate_corr - $gran_totale_uscite_corr, 2, ',', '.')) . '</td><td class="text-right">€ ' . esc_html(number_format($gran_totale_entrate_prec - $gran_totale_uscite_prec, 2, ',', '.')) . '</td></tr>';
            
            // SEZIONE CASSA
            echo '<tr><td colspan="6" class="section-title" style="margin-top: 20px;">CASSA E BANCA</td></tr>';
            $saldi = $this->report_service->get_saldi_conti($year);
            $tot_cassa = 0;
            foreach ($saldi as $c) {
                echo '<tr><td colspan="3"></td><td>Saldo finale ' . esc_html($c['nome']) . ' al 31/12/' . esc_html((string) $year) . '</td><td class="text-right">€ ' . esc_html(number_format($c['saldo'], 2, ',', '.')) . '</td><td></td></tr>';
                $tot_cassa += $c['saldo'];
            }
            echo '<tr class="totale-row"><td colspan="3"></td><td>TOTALE DISPONIBILITÀ LIQUIDE</td><td class="text-right">€ ' . esc_html(number_format($tot_cassa, 2, ',', '.')) . '</td><td></td></tr>';

            echo '</tbody></table>';

        } elseif ($tab === 'raccolte') {
            // === RENDER RACCOLTA OCCASIONALE ===
            
            $raccolte_list = $this->raccolte->get_all();
            $raccolta_id = isset($_GET['raccolta_id']) ? absint(wp_unslash($_GET['raccolta_id'])) : ($raccolte_list[0]['id'] ?? 0);

            echo '<div class="tc-no-print" style="margin-bottom: 20px;">';
            echo '<form method="get">
                    <input type="hidden" name="page" value="terzoconto-report" />
                    <input type="hidden" name="tab" value="raccolte" />
                    <strong>' . esc_html__('Seleziona Raccolta:', 'terzoconto-rendiconto-ets') . '</strong> <select name="raccolta_id">';
            foreach ($raccolte_list as $r) {
                echo '<option value="' . esc_attr((string) $r['id']) . '" ' . selected($raccolta_id, (int) $r['id'], false) . '>' . esc_html($r['nome']) . '</option>';
            }
            echo '</select> ' . wp_kses_post(get_submit_button(__('Mostra Report', 'terzoconto-rendiconto-ets'), 'secondary', '', false));
            echo      '</form></div>';

            if ($raccolta_id > 0) {
                $raccolta = $this->raccolte->find_by_id($raccolta_id);
                $dati = $this->report_service->get_dati_raccolta($raccolta_id);

                echo '<div class="tc-report-header">';
                echo '<h4>RENDICONTO DELLA SINGOLA RACCOLTA PUBBLICA DI FONDI OCCASIONALE<br>REDATTO AI SENSI DELL’ART. 87, COMMA 6 E ART. 79 D.LGS. 117/2017</h4>';
                echo '<h2>' . esc_html($nome_ente) . '</h2>';
                echo '<h3>C.F. ' . esc_html($cf_ente) . '</h3>';
                echo '<br><h3>RENDICONTO DELLA SINGOLA RACCOLTA FONDI</h3>';
                echo '<p><strong>' . esc_html($raccolta['nome']) . '</strong><br>';
                echo 'Durata della raccolta: dal ' . esc_html(wp_date('d/m/Y', strtotime((string) $raccolta['data_inizio']))) . ' al ' . esc_html($raccolta['data_fine'] ? wp_date('d/m/Y', strtotime((string) $raccolta['data_fine'])) : __('In corso', 'terzoconto-rendiconto-ets')) . '</p>';
                echo '</div>';

                echo '<div style="max-width: 600px; margin: 0 auto; font-family: sans-serif;">';
                
                // Entrate
                echo '<p><strong>a) Proventi / entrate della raccolta fondi occasionale</strong></p>';
                echo '<table width="100%" style="margin-left: 20px;">';
                foreach ($dati['entrate'] as $e) {
                    echo '<tr><td>- ' . esc_html($e['categoria']) . '</td><td align="right">€ ' . esc_html(number_format($e['totale'], 2, ',', '.')) . '</td></tr>';
                }
                echo '<tr><td align="right"><strong>Totale a)</strong></td><td align="right"><strong>€ ' . esc_html(number_format($dati['totale_entrate'], 2, ',', '.')) . '</strong></td></tr>';
                echo '</table>';

                // Uscite
                echo '<p style="margin-top:20px;"><strong>b) Oneri / uscite per la raccolta fondi occasionale</strong></p>';
                echo '<table width="100%" style="margin-left: 20px;">';
                foreach ($dati['uscite'] as $u) {
                    echo '<tr><td>- ' . esc_html($u['categoria']) . '</td><td align="right">€ ' . esc_html(number_format($u['totale'], 2, ',', '.')) . '</td></tr>';
                }
                echo '<tr><td align="right"><strong>Totale b)</strong></td><td align="right"><strong>€ ' . esc_html(number_format($dati['totale_uscite'], 2, ',', '.')) . '</strong></td></tr>';
                echo '</table>';

                // Risultato
                echo '<h3 style="text-align:center; margin-top: 20px; padding: 10px; border-top: 1px solid #000; border-bottom: 1px solid #000;">Risultato della singola raccolta (a-b) &nbsp;&nbsp;&nbsp; € ' . esc_html(number_format($dati['risultato'], 2, ',', '.')) . '</h3>';

                // Relazione Illustrativa
                echo '<h4 style="margin-top: 40px;">RELAZIONE ILLUSTRATIVA</h4>';
                echo '<div style="text-align: justify; line-height: 1.6;">';
				echo wp_kses_post(
				    wpautop(
				        esc_html(
				            $raccolta['relazione_illustrativa'] ?: 'Nessuna relazione inserita per questa raccolta. Modifica la raccolta per aggiungere i dettagli narrativi.'
				        )
				    )
				);
                echo '</div>';
                
                echo '<div style="margin-top: 60px; text-align: right;">';
                echo '<p>Data: ' . esc_html(wp_date('d/m/Y')) . '</p>';
                echo '<p>Firma del Rappresentante Legale<br>___________________________</p>';
                echo '</div>';
                
                echo '</div>';
            }
        }

        echo '</div>'; // Chiude wrap
    }

    private function render_movimento_form(array $categorie, array $conti, array $raccolte, array $anagrafiche, ?array $movimento = null): void {

		$is_edit = is_array($movimento);

		$selected_anagrafica = (int) ($movimento['anagrafica_id'] ?? 0);
		$selected_anagrafica_label = '';

		foreach ($anagrafiche as $anagrafica) {
			if ((int) ($anagrafica['id'] ?? 0) === $selected_anagrafica) {
				$selected_anagrafica_label = $this->format_anagrafica_label($anagrafica);
				break;
			}
		}

		echo '<form method="post">';
		wp_nonce_field('terzoconto_action_nonce');

		echo '<input type="hidden" name="terzoconto_action" value="' . esc_attr($is_edit ? 'update_movimento' : 'add_movimento') . '" />';

		if ($is_edit) {
			echo '<input type="hidden" name="id" value="' . esc_attr((string) $movimento['id']) . '" />';
		}

		echo '<div class="terzoconto-movimento-grid">';

		// DATA
		echo '<p><label>' . esc_html__('Data movimento', 'terzoconto-rendiconto-ets') . '</label><br />
			<input type="date" name="data_movimento" required value="' . esc_attr((string) ($movimento['data_movimento'] ?? gmdate('Y-m-d'))) . '" /></p>';

		// IMPORTO
		echo '<p><label>' . esc_html__('Importo', 'terzoconto-rendiconto-ets') . '</label><br />
			<input type="text" name="importo" required value="' . esc_attr((string) ($movimento['importo'] ?? '')) . '" /></p>';

		// TIPO
		$tipo = $movimento['tipo'] ?? 'entrata';
		echo '<p><label>' . esc_html__('Tipo', 'terzoconto-rendiconto-ets') . '</label><br />
			<select name="tipo">
				<option value="entrata"' . selected($tipo, 'entrata', false) . '>' . esc_html__('Entrata', 'terzoconto-rendiconto-ets') . '</option>
				<option value="uscita"' . selected($tipo, 'uscita', false) . '>' . esc_html__('Uscita', 'terzoconto-rendiconto-ets') . '</option>
			</select></p>';

		// CATEGORIA
		$selected_categoria = (int) ($movimento['categoria_associazione_id'] ?? 0);
		echo '<p><label>' . esc_html__('Categoria', 'terzoconto-rendiconto-ets') . '</label><br />';
		echo wp_kses(
            $this->render_categoria_select_html($categorie, 'categoria_associazione_id', $selected_categoria, true),
            [
                'select' => [
                    'name' => true,
                    'required' => true,
                ],
                'optgroup' => [
                    'label' => true,
                ],
                'option' => [
                    'value' => true,
                    'selected' => true,
                ],
            ]
        );
		echo '</p>';

		// CONTO
		$selected_conto = (int) ($movimento['conto_id'] ?? 0);
		echo '<p><label>' . esc_html__('Conto', 'terzoconto-rendiconto-ets') . '</label><br /><select name="conto_id" required>';
		echo '<option value="0">' . esc_html__('Seleziona conto', 'terzoconto-rendiconto-ets') . '</option>';
		foreach ($conti as $conto) {
			echo '<option value="' . esc_attr($conto['id']) . '"' . selected($selected_conto, (int)$conto['id'], false) . '>'
				. esc_html($conto['nome']) .
			'</option>';
		}
		echo '</select></p>';

		// RACCOLTA (GIÀ CORRETTA)
		echo '<p><label>' . esc_html__('Raccolta fondi', 'terzoconto-rendiconto-ets') . '</label><br /><select name="raccolta_fondi_id">';
		echo '<option value="0">' . esc_html__('Nessuna raccolta', 'terzoconto-rendiconto-ets') . '</option>';
		$selected_raccolta = (int) ($movimento['raccolta_fondi_id'] ?? 0);
		foreach ($raccolte as $raccolta) {
			echo '<option value="' . esc_attr($raccolta['id']) . '"' . selected($selected_raccolta, (int)$raccolta['id'], false) . '>'
				. esc_html($raccolta['nome']) .
			'</option>';
		}
		echo '</select></p>';

		//ANAGRAFICA (FIX VERO)
		echo '<p><label>' . esc_html__('Anagrafica', 'terzoconto-rendiconto-ets') . '</label><br />';
		echo '<select name="anagrafica_id" id="terzoconto-anagrafica-select">';

		echo '<option value="0"></option>'; // per allowClear

		foreach ($anagrafiche as $anagrafica) {

			$label = $this->format_anagrafica_label($anagrafica);

			echo '<option value="' . esc_attr($anagrafica['id']) . '" ' .
				selected($selected_anagrafica, (int)$anagrafica['id'], false) . '>' .
				esc_html($label) .
			'</option>';
		}

		echo '</select></p>';

		// DESCRIZIONE
		echo '<p><label>' . esc_html__('Descrizione', 'terzoconto-rendiconto-ets') . '</label><br />
			<input type="text" name="descrizione" value="' . esc_attr((string) ($movimento['descrizione'] ?? '')) . '" /></p>';
			
		$stato_selezionato = $movimento['stato'] ?? 'attivo';
		echo '<p><label>' . esc_html__('Stato del Movimento', 'terzoconto-rendiconto-ets') . '</label><br />
			<select name="stato">
				<option value="attivo"' . selected($stato_selezionato, 'attivo', false) . '>' . esc_html__('Attivo', 'terzoconto-rendiconto-ets') . '</option>
				<option value="annullato"' . selected($stato_selezionato, 'annullato', false) . '>' . esc_html__('Annullato', 'terzoconto-rendiconto-ets') . '</option>
			</select></p>';

		echo '</div>';

		submit_button($is_edit ? __('Aggiorna movimento', 'terzoconto-rendiconto-ets') : __('Aggiungi movimento', 'terzoconto-rendiconto-ets'));

		echo '</form><hr />';
	}

    private function get_categoria_optgroup_label(string $tipo, string $area): string {
        $type_labels = [
            'E' => __('Entrate', 'terzoconto-rendiconto-ets'),
            'U' => __('Uscite', 'terzoconto-rendiconto-ets'),
        ];

        $area_labels = [
            'A' => __('Attività di interesse generale', 'terzoconto-rendiconto-ets'),
            'B' => __('Attività diverse', 'terzoconto-rendiconto-ets'),
            'C' => __('Attività raccolta fondi', 'terzoconto-rendiconto-ets'),
            'D' => __('Attività finanziarie/patrimoniali', 'terzoconto-rendiconto-ets'),
            'E' => __('Supporto generale', 'terzoconto-rendiconto-ets'),
        ];

        $type_label = $type_labels[$tipo] ?? __('Categorie', 'terzoconto-rendiconto-ets');
        $area_label = $area_labels[$area] ?? __('Area non specificata', 'terzoconto-rendiconto-ets');

        return sprintf('%s - %s (%s)', $type_label, $area_label, $area !== '' ? $area : '-');
    }

	private function render_categoria_select_html(array $categorie, string $name, int $selected = 0, bool $required = false): string {

	    $html = '<select name="'.esc_attr($name).'" '.($required ? 'required' : '').'>';
	
	    $html .= '<option value="">' . esc_html__('-- categoria --', 'terzoconto-rendiconto-ets') . '</option>';
	
	    $grouped_categories = [];
	
	    foreach ($categorie as $cat) {
	
	        $group_label = $this->get_categoria_optgroup_label(
	            (string) ($cat['modello_d_tipo'] ?? ''),
	            (string) ($cat['modello_d_area'] ?? '')
	        );
	
	        if (!isset($grouped_categories[$group_label])) {
	            $grouped_categories[$group_label] = [];
	        }
	
	        $grouped_categories[$group_label][] = $cat;
	    }
	
	    foreach ($grouped_categories as $group_label => $group_categories) {
	
	        $html .= '<optgroup label="'.esc_attr($group_label).'">';
	
	        foreach ($group_categories as $cat) {
	
	            $category_code = trim((string) (($cat['modello_d_tipo'] ?? '') . ($cat['modello_d_codice'] ?? '')));
	            $category_label = $category_code !== '' 
	                ? $category_code . ' - ' . $cat['nome'] 
	                : $cat['nome'];
	
	            $html .= '<option value="'.esc_attr($cat['id']).'" '.selected($selected, (int)$cat['id'], false).'>'
	                .esc_html($category_label).
	            '</option>';
	        }
	
	        $html .= '</optgroup>';
	    }
	
	    $html .= '</select>';
	
	    return $html;
	}

    private function render_movimenti_filters(string $stato_filter): void {
        echo '<form method="get" class="terzoconto-filter-form">';
        echo '<input type="hidden" name="page" value="terzoconto" />';
        wp_nonce_field(
		    'terzoconto_filter_nonce',
		    '_wpnonce',
		    false
		);
        echo '<label for="stato_movimento" style="margin-right:8px;">' . esc_html__('Filtra per stato', 'terzoconto-rendiconto-ets') . '</label>';
        echo '<select name="stato_movimento" id="stato_movimento">';
        echo '<option value="">' . esc_html__('Tutti gli stati', 'terzoconto-rendiconto-ets') . '</option>';
        echo '<option value="attivo"' . selected($stato_filter, 'attivo', false) . '>' . esc_html__('Attivo', 'terzoconto-rendiconto-ets') . '</option>';
        echo '<option value="annullato"' . selected($stato_filter, 'annullato', false) . '>' . esc_html__('Annullato', 'terzoconto-rendiconto-ets') . '</option>';
        echo '</select> ';
        submit_button(__('Filtra', 'terzoconto-rendiconto-ets'), 'secondary', '', false);
        echo '</form>';
    }

    private function sanitize_movimento_data(array $source): array {
        $tipo = sanitize_text_field(wp_unslash($source['tipo'] ?? 'entrata'));
        if (! in_array($tipo, ['entrata', 'uscita'], true)) {
            $tipo = 'entrata';
        }
		
		$stato = sanitize_text_field(wp_unslash($source['stato'] ?? 'attivo'));
        if (! in_array($stato, ['attivo', 'annullato'], true)) {
            $stato = 'attivo';
        }

        return [
            'data_movimento' => sanitize_text_field(wp_unslash($source['data_movimento'] ?? '')),
            'importo' => (float) str_replace(',', '.', (string) wp_unslash($source['importo'] ?? '0')),
            'tipo' => $tipo,
			'stato' => $stato,
            'categoria_associazione_id' => absint(wp_unslash($source['categoria_associazione_id'] ?? 0)),
            'conto_id' => absint(wp_unslash($source['conto_id'] ?? 0)),
            'raccolta_fondi_id' => absint(wp_unslash($source['raccolta_fondi_id'] ?? 0)),
            'anagrafica_id' => absint(wp_unslash($source['anagrafica_id'] ?? 0)),
            'descrizione' => sanitize_text_field(wp_unslash($source['descrizione'] ?? '')),
        ];
    }

    private function get_movimento_stato_filter(): string {
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? ''));
        if (! wp_verify_nonce($nonce, 'terzoconto_filter_nonce')) {
		    return '';
		}

        $stato = sanitize_text_field(wp_unslash($_GET['stato_movimento'] ?? ''));
        return in_array($stato, ['attivo', 'annullato'], true) ? $stato : '';
    }

    private function format_anagrafica_label(array $anagrafica): string {
        if (($anagrafica['tipo'] ?? '') === 'azienda') {
            return trim((string) ($anagrafica['ragione_sociale'] ?? ''));
        }

        $label = trim((string) (($anagrafica['nome'] ?? '') . ' ' . ($anagrafica['cognome'] ?? '')));
        return $label !== '' ? $label : (string) __('Anagrafica senza nome', 'terzoconto-rendiconto-ets');
    }

    private function handle_import_preview(): void {
		$tmp_name = (string) ($_FILES['csv_file']['tmp_name'] ?? '');
		if (! isset($_FILES['csv_file']) || $tmp_name === '') {
		add_settings_error('terzoconto', 'import_missing_file', __('File mancante', 'terzoconto-rendiconto-ets'), 'error');
			return;
		}
		if (! isset($_FILES['csv_file']['error']) || (int) $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
			add_settings_error('terzoconto', 'import_upload_error', __('Errore upload file', 'terzoconto-rendiconto-ets'), 'error');
			return;
		}
		if (! isset($_FILES['csv_file']['size']) || (int) $_FILES['csv_file']['size'] > 2 * 1024 * 1024) {
			add_settings_error('terzoconto', 'import_file_too_large', __('File troppo grande (max 2MB)', 'terzoconto-rendiconto-ets'), 'error');
			return;
		}
		$file_name = sanitize_file_name((string) ($_FILES['csv_file']['name'] ?? ''));
		$file_ext = strtolower((string) pathinfo($file_name, PATHINFO_EXTENSION));
		if ($file_ext !== 'csv') {
			add_settings_error('terzoconto', 'import_invalid_extension', __('Estensione file non valida (solo .csv)', 'terzoconto-rendiconto-ets'), 'error');
			return;
		}
		$allowed_mimes = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
		$wp_filetype = wp_check_filetype($file_name);
		$mime_ok_wp = in_array((string) ($wp_filetype['type'] ?? ''), $allowed_mimes, true);
		$mime_ok_finfo = false;
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo) {
				$detected_mime = (string) finfo_file($finfo, $tmp_name);
				finfo_close($finfo);
				$mime_ok_finfo = in_array($detected_mime, $allowed_mimes, true);
			}
		}
		if (! $mime_ok_wp && ! $mime_ok_finfo) {
			add_settings_error('terzoconto', 'import_invalid_mime', __('Tipo file non valido', 'terzoconto-rendiconto-ets'), 'error');
			return;
		}

		$provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? 'generico'));

		$rows = $this->import_service->parse_csv($tmp_name, $provider);

		if ($rows === []) {
			add_settings_error('terzoconto', 'import_empty', __('CSV vuoto', 'terzoconto-rendiconto-ets'), 'error');
			return;
		}

		$valid_rows = $this->import_service->get_valid_rows($rows);
		$duplicates = $this->import_service->detect_duplicates($rows, $this->movimenti->get_all());

		set_transient($this->get_import_preview_transient_key(), [
			'rows' => $rows,
			'valid_rows' => $valid_rows,
			'duplicates' => $duplicates,
		]);

		add_settings_error('terzoconto', 'import_ok', __('Anteprima generata', 'terzoconto-rendiconto-ets'), 'updated');
	}

    private function handle_import_commit(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }


		if (! current_user_can('manage_options')) {
			return;
		}

		$rows = $_POST['rows'] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }

		if (empty($rows)) {
			add_settings_error('terzoconto', 'import_no_data', __('Nessun dato da importare', 'terzoconto-rendiconto-ets'), 'error');
			return;
		}

		$imported = 0;

		foreach ($rows as $row) {

			// salta righe senza categoria
			if (empty($row['categoria_id'])) {
				continue;
			}

            $data_movimento = sanitize_text_field(wp_unslash($row['data_movimento'] ?? ''));
            $importo = (float) str_replace(',', '.', (string) wp_unslash($row['importo'] ?? '0'));
            $tipo = sanitize_text_field(wp_unslash($row['tipo'] ?? ''));
            $categoria_id = absint(wp_unslash($row['categoria_id'] ?? 0));
            $conto_id = absint(wp_unslash($row['conto_id'] ?? 0));
            $descrizione = sanitize_text_field(wp_unslash($row['descrizione'] ?? ''));

            if (! $this->validator->is_valid_date($data_movimento)) {
                continue;
            }

            if (! $this->validator->is_valid_money($importo)) {
                continue;
            }

            if (! in_array($tipo, ['entrata', 'uscita'], true) || $categoria_id <= 0 || $conto_id <= 0) {
                continue;
            }

			$this->movimenti->create([
				'data_movimento' => $data_movimento,
				'importo' => $importo,
				'tipo' => $tipo,
				'categoria_associazione_id' => $categoria_id,
				'conto_id' => $conto_id,
				'raccolta_fondi_id' => 0,
				'anagrafica_id' => 0,
				'descrizione' => $descrizione,
			]);

			$imported++;
		}

		delete_transient($this->get_import_preview_transient_key());
		/* translators: %d = number of imported movements */
		add_settings_error('terzoconto', 'import_done', esc_html(sprintf(__('Import completato: %d movimenti', 'terzoconto-rendiconto-ets'), $imported)), 'updated');
	}

    private function get_import_preview_transient_key(): string {
		return 'terzoconto_import_preview_' . get_current_user_id();
	}

    private function handle_create_conto(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        $data = $this->sanitize_conto_data($_POST);
        $this->submitted_conto = $data;

        if (! $this->validate_conto_data($data)) {
            return;
        }

        $created = $this->conti->create($data['nome'], $data['descrizione'], $data['tracciabile'], $data['attivo']);
        $status = $created ? 'created' : 'error';
        wp_safe_redirect(add_query_arg('tc_conto_status', $status, admin_url('admin.php?page=terzoconto-conti')));
        exit;
    }

    private function handle_update_conto(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $data = $this->sanitize_conto_data($_POST);
        if ($id > 0) {
            $data['id'] = $id;
        }
        $this->submitted_conto = $data;

        if ($id <= 0 || ! $this->validate_conto_data($data)) {
            return;
        }

        $updated = $this->conti->update($id, $data['nome'], $data['descrizione'], $data['tracciabile'], $data['attivo']);
        $status = $updated ? 'updated' : 'error';
        wp_safe_redirect(add_query_arg('tc_conto_status', $status, admin_url('admin.php?page=terzoconto-conti')));
        exit;
    }

    private function handle_delete_conto(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        if ($id <= 0) {
            wp_safe_redirect(add_query_arg('tc_conto_status', 'error', admin_url('admin.php?page=terzoconto-conti')));
            exit;
        }

        if (! $this->conti->can_delete($id)) {
            wp_safe_redirect(add_query_arg('tc_conto_status', 'cannot_delete', admin_url('admin.php?page=terzoconto-conti')));
            exit;
        }

        $deleted = $this->conti->delete($id);
        $status = $deleted ? 'deleted' : 'error';
        wp_safe_redirect(add_query_arg('tc_conto_status', $status, admin_url('admin.php?page=terzoconto-conti')));
        exit;
    }

    private function sanitize_conto_data(array $source): array {
        return [
            'nome' => sanitize_text_field(wp_unslash($source['nome'] ?? '')),
            'descrizione' => sanitize_text_field(wp_unslash($source['descrizione'] ?? '')),
            'tracciabile' => isset($source['tracciabile']) ? 1 : 0,
            'attivo' => isset($source['attivo']) ? 1 : 0,
        ];
    }

    private function validate_conto_data(array $data): bool {
        if ($data['nome'] === '') {
            add_settings_error('terzoconto_conti', 'conto_nome', __('Il nome conto è obbligatorio.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        if (! $this->validator->is_valid_conto_name($data['nome'])) {
            add_settings_error('terzoconto_conti', 'conto_nome_length', __('Il nome conto deve contenere tra 2 e 120 caratteri.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        if (! $this->validator->is_valid_short_text($data['descrizione'])) {
            add_settings_error('terzoconto_conti', 'conto_descrizione_length', __('La descrizione conto può contenere al massimo 255 caratteri.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        return true;
    }

    public function render_conti_notice(): void {
        $status = sanitize_text_field(wp_unslash($_GET['tc_conto_status'] ?? ''));

        if ($status === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Conto creato con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Conto aggiornato con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Conto eliminato con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'cannot_delete') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Non puoi eliminare il conto: è associato a uno o più movimenti.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Operazione sui conti non riuscita.', 'terzoconto-rendiconto-ets') . '</p></div>';
        }
    }

    public function get_conti_repository(): TerzoConto_Conti_Repository {
        return $this->conti;
    }

    private function handle_create_raccolta(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        $data = $this->sanitize_raccolta_data($_POST);
        $this->submitted_raccolta = $data;

        if (! $this->validate_raccolta_data($data)) {
            return;
        }

        $created = $this->raccolte->create($data);
        $status = $created ? 'created' : 'error';
        wp_safe_redirect(add_query_arg('tc_raccolta_status', $status, admin_url('admin.php?page=terzoconto-raccolte')));
        exit;
    }

    private function handle_update_raccolta(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        $data = $this->sanitize_raccolta_data($_POST);
        if ($id > 0) {
            $data['id'] = $id;
        }
        $this->submitted_raccolta = $data;

        if ($id <= 0 || ! $this->validate_raccolta_data($data)) {
            return;
        }

        $updated = $this->raccolte->update($id, $data);
        $status = $updated ? 'updated' : 'error';
        wp_safe_redirect(add_query_arg('tc_raccolta_status', $status, admin_url('admin.php?page=terzoconto-raccolte')));
        exit;
    }

    private function handle_delete_raccolta(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        $id = absint(wp_unslash($_POST['id'] ?? 0));
        if ($id <= 0) {
            wp_safe_redirect(add_query_arg('tc_raccolta_status', 'error', admin_url('admin.php?page=terzoconto-raccolte')));
            exit;
        }

        if (! $this->raccolte->can_delete($id)) {
            wp_safe_redirect(add_query_arg('tc_raccolta_status', 'cannot_delete', admin_url('admin.php?page=terzoconto-raccolte')));
            exit;
        }

        $deleted = $this->raccolte->delete($id);
        $status = $deleted ? 'deleted' : 'error';
        wp_safe_redirect(add_query_arg('tc_raccolta_status', $status, admin_url('admin.php?page=terzoconto-raccolte')));
        exit;
    }

    private function sanitize_raccolta_data(array $source): array {
        return [
            'nome' => sanitize_text_field(wp_unslash($source['nome'] ?? '')),
            'descrizione' => sanitize_text_field(wp_unslash($source['descrizione'] ?? '')),
            'data_inizio' => sanitize_text_field(wp_unslash($source['data_inizio'] ?? '')),
            'data_fine' => sanitize_text_field(wp_unslash($source['data_fine'] ?? '')),
            'stato' => sanitize_text_field(wp_unslash($source['stato'] ?? 'aperta')),
            'relazione_illustrativa' => sanitize_textarea_field(wp_unslash($source['relazione_illustrativa'] ?? '')),
        ];
    }

    private function validate_raccolta_data(array $data): bool {
        if ($data['nome'] === '') {
            add_settings_error('terzoconto_raccolte', 'raccolta_nome', __('Il nome raccolta è obbligatorio.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        if ($data['data_inizio'] === '') {
            add_settings_error('terzoconto_raccolte', 'raccolta_data_inizio', __('La data di inizio è obbligatoria.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }


        if (! $this->validator->is_valid_date($data['data_inizio'])) {
            add_settings_error('terzoconto_raccolte', 'raccolta_data_inizio_format', __('La data di inizio non è valida.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        $allowed_status = ['aperta', 'chiusa'];
        if (! in_array($data['stato'], $allowed_status, true)) {
            add_settings_error('terzoconto_raccolte', 'raccolta_stato', __('Lo stato selezionato non è valido.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        if ($data['data_fine'] !== '' && ! $this->validator->is_valid_date($data['data_fine'])) {
            add_settings_error('terzoconto_raccolte', 'raccolta_data_fine_format', __('La data di fine non è valida.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        if ($data['data_fine'] !== '' && $data['data_fine'] < $data['data_inizio']) {
            add_settings_error('terzoconto_raccolte', 'raccolta_data_fine', __('La data di fine deve essere uguale o successiva alla data di inizio.', 'terzoconto-rendiconto-ets'), 'error');
            return false;
        }

        return true;
    }

    private function render_raccolte_notice(): void {
        $status = sanitize_text_field(wp_unslash($_GET['tc_raccolta_status'] ?? ''));

        if ($status === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Raccolta creata con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Raccolta aggiornata con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'deleted') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Raccolta eliminata con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'cannot_delete') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Non puoi eliminare la raccolta: è associata a uno o più movimenti.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Operazione sulle raccolte non riuscita.', 'terzoconto-rendiconto-ets') . '</p></div>';
        }
    }

    private function download_csv(): void {
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        $movimenti = $this->movimenti->get_all();
        foreach ($movimenti as &$movimento) {
            foreach (['id', 'data_movimento', 'importo', 'tipo', 'descrizione', 'stato'] as $field) {
                $movimento[$field] = $this->validator->sanitize_csv_cell((string) ($movimento[$field] ?? ''));
            }
        }
        unset($movimento);

        $csv = $this->report_service->export_csv_movimenti($movimenti);
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=terzoconto-movimenti.csv');
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
	
	private function handle_create_movimento(): void {
		if (! $this->security->assert_manage_capability()) {
            return;
        }

		$data = $this->sanitize_movimento_data($_POST);
		$this->submitted_movimento = $data;

		$result = $this->movimenti_service->create($data);

		if (is_wp_error($result)) {
			add_settings_error('terzoconto', 'create_error', $result->get_error_message(), 'error');
			return;
		}

		wp_safe_redirect(admin_url('admin.php?page=terzoconto&tc_movimento_status=created'));
		exit;
	}
	
	private function handle_update_movimento(): void {
		if (! $this->security->assert_manage_capability()) {
            return;
        }

		$id = absint(wp_unslash($_POST['id'] ?? 0));

		$data = $this->sanitize_movimento_data($_POST);
		$this->submitted_movimento = $data;

		$result = $this->movimenti_service->update($id, $data);

		if (is_wp_error($result)) {
			add_settings_error('terzoconto', 'update_error', $result->get_error_message(), 'error');
			return;
		}

		wp_safe_redirect(admin_url('admin.php?page=terzoconto&tc_movimento_status=updated'));
		exit;
	}
	
	private function handle_annulla_movimento(): void {
		if (! $this->security->assert_manage_capability()) {
            return;
        }

		$id = absint(wp_unslash($_GET['id'] ?? 0));

		if ($id <= 0) {
			return;
		}

		$this->movimenti->mark_annullato($id);

		wp_safe_redirect(admin_url('admin.php?page=terzoconto&tc_movimento_status=annullato'));
		exit;
	}
	
	private function handle_bulk_update_movimenti(): void {
        global $wpdb;
        if (! $this->security->assert_manage_capability()) {
            return;
        }

        // Acquisizione e pulizia degli ID (Nessun controllo nonce qui, è già stato fatto!)
        $ids = wp_unslash($_POST['movimento_ids'] ?? []);
        if (empty($ids) || !is_array($ids)) {
            wp_safe_redirect(admin_url('admin.php?page=terzoconto&tc_bulk=no_ids'));
            exit;
        }

        $clean_ids = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean_ids[] = $id;
            }
        }

        if (empty($clean_ids)) {
            wp_safe_redirect(admin_url('admin.php?page=terzoconto&tc_bulk=no_ids'));
            exit;
        }

        // 3. Acquisizione dei campi da modificare
        $fields = [];
        if (! empty($_POST['bulk_categoria_id'])) {
            $fields['categoria_associazione_id'] = absint(wp_unslash($_POST['bulk_categoria_id']));
        }
        if (! empty($_POST['bulk_conto_id'])) {
            $fields['conto_id'] = absint(wp_unslash($_POST['bulk_conto_id']));
        }
        if (! empty($_POST['bulk_raccolta_id'])) {
            $fields['raccolta_fondi_id'] = absint(wp_unslash($_POST['bulk_raccolta_id']));
        }
        if (! empty($_POST['bulk_anagrafica_id'])) {
            $fields['anagrafica_id'] = absint(wp_unslash($_POST['bulk_anagrafica_id']));
        }

        if (empty($fields)) {
            wp_safe_redirect(admin_url('admin.php?page=terzoconto&tc_bulk=no_fields'));
            exit;
        }

        // 4. Preparazione della Query
        $table = $wpdb->prefix . 'terzoconto_movimenti';
        
        // Aggiungiamo la data di aggiornamento
        $fields['updated_at'] = current_time('mysql');

		$set_parts = [];
		$set_values = [];

		if (isset($fields['categoria_associazione_id'])) {
			$set_parts[] = 'categoria_associazione_id = %d';
			$set_values[] = (int) $fields['categoria_associazione_id'];
		}

		if (isset($fields['conto_id'])) {
			$set_parts[] = 'conto_id = %d';
			$set_values[] = (int) $fields['conto_id'];
		}

		if (isset($fields['raccolta_fondi_id'])) {
			$set_parts[] = 'raccolta_fondi_id = %d';
			$set_values[] = (int) $fields['raccolta_fondi_id'];
		}

		if (isset($fields['anagrafica_id'])) {
			$set_parts[] = 'anagrafica_id = %d';
			$set_values[] = (int) $fields['anagrafica_id'];
		}

		$set_parts[] = 'updated_at = %s';
		$set_values[] = (string) current_time('mysql');

		$table = esc_sql($table);
		$ids_placeholders = implode(',', array_fill(0, count($clean_ids), '%d'));

		$query = "UPDATE {$table} SET ";
		$params = [];

		if (isset($fields['categoria_associazione_id'])) {
			$query .= 'categoria_associazione_id = %d, ';
			$params[] = (int) $fields['categoria_associazione_id'];
		}

		if (isset($fields['conto_id'])) {
			$query .= 'conto_id = %d, ';
			$params[] = (int) $fields['conto_id'];
		}

		if (isset($fields['raccolta_fondi_id'])) {
			$query .= 'raccolta_fondi_id = %d, ';
			$params[] = (int) $fields['raccolta_fondi_id'];
		}

		if (isset($fields['anagrafica_id'])) {
			$query .= 'anagrafica_id = %d, ';
			$params[] = (int) $fields['anagrafica_id'];
		}

		$query .= 'updated_at = %s ';
		$params[] = (string) current_time('mysql');

		$query .= "WHERE id IN ($ids_placeholders)";

		$params = array_merge($params, $clean_ids);
		
		
		/*
		 * phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter
		 */
		$updated = $wpdb->query(
			$wpdb->prepare(
				$query,
				...$params
			)
		);
		/* phpcs:enable */
		
        // Trappola per errori MySQL
        if ($updated === false) {
            add_settings_error(
                'terzoconto',
                'bulk_update_error',
                __('Operazione non riuscita. Riprova più tardi.', 'terzoconto-rendiconto-ets'),
                'error'
            );
            wp_safe_redirect(admin_url('admin.php?page=terzoconto'));
            exit;
        }

        // 6. Redirect finale
        wp_safe_redirect(admin_url('admin.php?page=terzoconto&tc_bulk=done&updated=' . (int)$updated));
        exit;
    }
	
	private function render_support_box(): void{
		?>
		<div class="terzoconto-support-box">
			<h2><?php echo esc_html__('☕ Supporta il progetto', 'terzoconto-rendiconto-ets'); ?></h2>

			<p>
				<?php echo esc_html__('Questo plugin è sviluppato e mantenuto gratuitamente.', 'terzoconto-rendiconto-ets'); ?><br>
				<?php echo esc_html__('Se ti è utile, puoi supportarlo:', 'terzoconto-rendiconto-ets'); ?>
			</p>

			<p>
				<a href="<?php echo esc_url('https://github.com/sponsors/mjfan80'); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
					<?php echo esc_html__('❤️ GitHub Sponsors', 'terzoconto-rendiconto-ets'); ?>
				</a>
			</p>

			<p>
				<a href="<?php echo esc_url('https://www.buymeacoffee.com/gabrieleprandini'); ?>" target="_blank" rel="noopener noreferrer" class="button">
					<?php echo esc_html__('☕ Offrimi un caffè', 'terzoconto-rendiconto-ets'); ?>
				</a>
			</p>

			<p style="font-size:12px; opacity:0.7;">
				<?php echo esc_html__('Sviluppato da Gabriele Prandini (mjfan80)', 'terzoconto-rendiconto-ets'); ?>
			</p>
		</div>
		<?php
	}
	
	
}
