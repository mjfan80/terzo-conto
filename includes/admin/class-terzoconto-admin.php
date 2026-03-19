<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once TERZOCONTO_PLUGIN_DIR . 'includes/admin/class-terzoconto-movimenti-list-table.php';

class TerzoConto_Admin {
    private ?array $submitted_movimento = null;

    public function __construct(
        private TerzoConto_Movimenti_Repository $movimenti,
        private TerzoConto_Categorie_Repository $categorie,
        private TerzoConto_Conti_Repository $conti,
        private TerzoConto_Raccolte_Repository $raccolte,
        private TerzoConto_Anagrafiche_Repository $anagrafiche,
        private TerzoConto_Import_Service $import_service,
        private TerzoConto_Report_Service $report_service
    ) {
    }

    public function hooks(): void {
		add_action('admin_menu', [$this, 'register_menu']);
		add_action('admin_init', [$this, 'handle_post_actions']); // ← TORNA QUI
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
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

			case 'import_preview':

				if (! isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
					add_settings_error('terzoconto', 'import_missing_file', 'File mancante', 'error');
					return;
				}

				$provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? 'generico'));

				$rows = $this->import_service->parse_csv($_FILES['csv_file']['tmp_name'], $provider);

				if ($rows === []) {
					add_settings_error('terzoconto', 'import_empty', 'CSV vuoto', 'error');
					return;
				}

				$valid_rows = $this->import_service->get_valid_rows($rows);
				$duplicates = $this->import_service->detect_duplicates($rows, $this->movimenti->get_all());

				// 👉 QUI SALVIAMO IN MEMORIA (NON DB)
				$this->submitted_movimento = [
					'rows' => $rows,
					'valid_rows' => $valid_rows,
					'duplicates' => $duplicates,
				];

				add_settings_error('terzoconto', 'import_ok', 'Anteprima generata', 'updated');

				break;

			case 'import_commit':
				$this->handle_import_commit();
				break;
		}
	}

    public function render_movimenti(): void {
        $stato_filter = $this->get_movimento_stato_filter();
        $movimenti = $this->movimenti->get_all();
        $categorie = $this->categorie->get_associazione();
        $conti = $this->conti->get_all();
        $raccolte = $this->raccolte->get_aperte();
        $anagrafiche = $this->anagrafiche->search('');

        $edit_id = absint($_GET['edit_movimento_id'] ?? 0);
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

        echo '<div class="wrap"><h1>TerzoConto - ' . esc_html__('Movimenti', 'terzo-conto') . '</h1>';
        settings_errors('terzoconto');
        $this->render_movimento_form($categorie, $conti, $raccolte, $anagrafiche, $movimento);
        $this->render_movimenti_filters($stato_filter);
        $table = new TerzoConto_Movimenti_List_Table($movimenti);
        $table->prepare_items();
        $table->display();
        echo '</div>';
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'toplevel_page_terzoconto') {
            return;
        }

        $config = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('terzoconto_search_anagrafiche_nonce'),
            'minChars' => 2,
            'noResults' => __('Nessuna anagrafica trovata.', 'terzo-conto'),
            'searching' => __('Ricerca in corso…', 'terzo-conto'),
        ];

        wp_add_inline_style('common', '.terzoconto-movimento-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:1100px}.terzoconto-movimento-grid p{margin:0}.terzoconto-ajax-results{display:none;border:1px solid #c3c4c7;background:#fff;max-height:220px;overflow:auto;position:absolute;z-index:1000;width:100%;box-sizing:border-box}.terzoconto-ajax-results button{display:block;width:100%;border:0;background:transparent;text-align:left;padding:8px 10px;cursor:pointer}.terzoconto-ajax-results button:hover,.terzoconto-ajax-results button:focus{background:#f0f0f1}.terzoconto-field-help{display:block;color:#646970;margin-top:4px}.terzoconto-anagrafica-search{position:relative}.terzoconto-filter-form{margin:16px 0}');
        wp_add_inline_script('jquery-core', 'window.terzoContoAnagrafiche=' . wp_json_encode($config) . ';jQuery(function($){var cfg=window.terzoContoAnagrafiche||{};var $search=$("#terzoconto-anagrafica-search");var $id=$("#anagrafica_id");var $results=$("#terzoconto-anagrafica-results");var xhr=null;function closeResults(){$results.hide().empty();}function selectItem(item){$id.val(item.id);$search.val(item.label);closeResults();}$(document).on("click",function(e){if(!$(e.target).closest(".terzoconto-anagrafica-search").length){closeResults();}});$(document).on("input","#terzoconto-anagrafica-search",function(){var term=$.trim($search.val());if(term.length===0){$id.val(0);closeResults();return;}if(term.length<(cfg.minChars||2)){closeResults();return;}if(xhr){xhr.abort();}$results.html("<div class=\"terzoconto-field-help\">"+(cfg.searching||"")+"</div>").show();xhr=$.get(cfg.ajaxUrl,{action:"terzoconto_search_anagrafiche",nonce:cfg.nonce,term:term}).done(function(items){$results.empty();if(!items||!items.length){$results.html("<div class=\"terzoconto-field-help\">"+(cfg.noResults||"")+"</div>").show();return;}$.each(items,function(_,item){var $btn=$("<button type=\"button\" />").text(item.label);$btn.on("click",function(){selectItem(item);});$results.append($btn);});$results.show();}).fail(function(){closeResults();});});$(document).on("change","#terzoconto-anagrafica-search",function(){if($.trim($search.val())===""){$id.val(0);}});});');
    }

    public function render_categorie(): void {
        $modello_d = $this->categorie->get_modello_d();
        $categorie = $this->categorie->get_associazione();

        echo '<div class="wrap"><h1>' . esc_html__('Categorie', 'terzo-conto') . '</h1>';
        echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field('terzoconto_action_nonce');
		echo '<input type="hidden" name="terzoconto_action" value="import_preview" />';
		echo '<p><select name="provider">
		<option value="generico">CSV generico</option>
		<option value="paypal">CSV PayPal</option>
		<option value="satispay">CSV Satispay</option>
		</select></p>';
		echo '<p><input type="file" name="csv_file" accept=".csv" required /></p>';
		submit_button(__('Carica e anteprima', 'terzo-conto'));
		echo '</form>';

        echo '<table class="widefat"><thead><tr><th>' . esc_html__('Nome', 'terzo-conto') . '</th><th>' . esc_html__('Modello D', 'terzo-conto') . '</th></tr></thead><tbody>';
        foreach ($categorie as $cat) {
            echo '<tr><td>' . esc_html($cat['nome']) . '</td><td>' . esc_html($cat['modello_d_codice'] . ' - ' . $cat['modello_d_nome']) . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    public function render_conti(): void {
        $conti = $this->conti->get_all();
        $edit_id = absint($_GET['edit_conto_id'] ?? 0);
        $conto = $edit_id > 0 ? $this->conti->find_by_id($edit_id) : null;
        $is_edit = is_array($conto);

        echo '<div class="wrap"><h1>' . esc_html__('Conti', 'terzo-conto') . '</h1>';
        echo '<form method="post">';
        wp_nonce_field('terzoconto_action_nonce');
        echo '<input type="hidden" name="terzoconto_action" value="' . esc_attr($is_edit ? 'update_conto' : 'add_conto') . '" />';
        if ($is_edit) {
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $conto['id']) . '" />';
        }
        echo '<p><input type="text" name="nome" required placeholder="' . esc_attr__('Nome conto', 'terzo-conto') . '" value="' . esc_attr((string) ($conto['nome'] ?? '')) . '" /></p>';
        echo '<p><input type="text" name="descrizione" placeholder="' . esc_attr__('Descrizione', 'terzo-conto') . '" value="' . esc_attr((string) ($conto['descrizione'] ?? '')) . '" /></p>';
        echo '<p><label><input type="checkbox" name="tracciabile" value="1" ' . checked((int) ($conto['tracciabile'] ?? 0), 1, false) . ' /> ' . esc_html__('Tracciabile', 'terzo-conto') . '</label></p>';
        echo '<p><label><input type="checkbox" name="attivo" value="1" ' . checked((int) ($conto['attivo'] ?? 1), 1, false) . ' /> ' . esc_html__('Attivo', 'terzo-conto') . '</label></p>';
        submit_button($is_edit ? __('Aggiorna conto', 'terzo-conto') : __('Aggiungi conto', 'terzo-conto'));
        echo '</form><hr /><ul>';
        foreach ($conti as $row) {
            $edit_url = add_query_arg(['page' => 'terzoconto-conti', 'edit_conto_id' => (int) $row['id']], admin_url('admin.php'));
            $meta = [];
            if (! empty($row['tracciabile'])) {
                $meta[] = __('tracciabile', 'terzo-conto');
            }
            if (empty($row['attivo'])) {
                $meta[] = __('non attivo', 'terzo-conto');
            }
            echo '<li><a href="' . esc_url($edit_url) . '">' . esc_html($row['nome']) . '</a>';
            if ($meta) {
                echo ' - ' . esc_html(implode(', ', $meta));
            }
            echo '</li>';
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

		$preview = $this->submitted_movimento;

		$categorie = $this->categorie->get_associazione();
		$conti = $this->conti->get_all();

		echo '<div class="wrap"><h1>Import CSV</h1>';

		settings_errors('terzoconto');

		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field('terzoconto_action_nonce');
		echo '<input type="hidden" name="terzoconto_action" value="import_preview" />';
		echo '<p><select name="provider">
			<option value="generico">CSV generico</option>
			<option value="paypal">CSV PayPal</option>
			<option value="satispay">CSV Satispay</option>
		</select></p>';
		echo '<p><input type="file" name="csv_file" accept=".csv" required /></p>';
		submit_button('Carica e anteprima');
		echo '</form>';

		if (is_array($preview) && isset($preview['rows'])) {

			$rows = $preview['rows'];
			$valid_rows = $preview['valid_rows'];
			$duplicates = $preview['duplicates'];

			echo '<h2>Anteprima</h2>';
			echo '<p>Righe valide: ' . count($valid_rows) . ' su ' . count($rows) . '</p>';

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
			<th>Esito</th>
			</tr>
			</thead><tbody>';

			foreach ($rows as $i => $row) {

				$is_dupe = in_array($i, $duplicates, true);
				$errors = $row['errors'] ?? [];

				echo '<tr>';

				echo '<td>' . ($i + 1) . '</td>';

				echo '<td><input type="date" name="rows['.$i.'][data_movimento]" value="' . esc_attr($row['data_movimento']) . '"></td>';

				echo '<td><input type="text" name="rows['.$i.'][importo]" value="' . esc_attr($row['importo']) . '"></td>';

				echo '<td>
					<select name="rows['.$i.'][tipo]">
						<option value="entrata" ' . selected($row['tipo'], 'entrata', false) . '>Entrata</option>
						<option value="uscita" ' . selected($row['tipo'], 'uscita', false) . '>Uscita</option>
					</select>
				</td>';

				echo '<td><input type="text" name="rows['.$i.'][descrizione]" value="' . esc_attr($row['descrizione']) . '" style="width:100%"></td>';

				echo '<td><select name="rows['.$i.'][categoria_id]" required>';
				echo '<option value="">-- categoria --</option>';

				$grouped_categories = [];

				foreach ($categorie as $cat) {

					$group_label = $this->get_categoria_optgroup_label(
						(string) ($cat['modello_d_tipo'] ?? ''),
						(string) ($cat['modello_d_area'] ?? '')
					);

					if (! isset($grouped_categories[$group_label])) {
						$grouped_categories[$group_label] = [];
					}

					$grouped_categories[$group_label][] = $cat;
				}

				foreach ($grouped_categories as $group_label => $group_categories) {

					echo '<optgroup label="' . esc_attr($group_label) . '">';

					foreach ($group_categories as $cat) {

						$category_code = trim((string) (($cat['modello_d_tipo'] ?? '') . ($cat['modello_d_codice'] ?? '')));
						$category_label = $category_code !== '' 
							? $category_code . ' - ' . $cat['nome'] 
							: $cat['nome'];

						echo '<option value="' . esc_attr($cat['id']) . '">' . esc_html($category_label) . '</option>';
					}

					echo '</optgroup>';
				}

				echo '</select></td>';

				$status = [];
				if ($errors) $status[] = implode(' ', $errors);
				if ($is_dupe) $status[] = 'Duplicato';
				if (! $status) $status[] = 'OK';

				echo '<td>' . implode(' | ', $status) . '</td>';

				echo '</tr>';
			}

			echo '</tbody></table>';

			submit_button('Importa tutto');

			echo '</form>';

			echo '<h3>Importa righe valide</h3>';

			echo '<form method="post">';
			wp_nonce_field('terzoconto_action_nonce');
			echo '<input type="hidden" name="terzoconto_action" value="import_commit" />';
			
			echo '<p><select name="categoria_associazione_id" required>';
			echo '<option value="0">Seleziona categoria</option>';
			foreach ($categorie as $categoria) {
			    echo '<option value="' . esc_attr($categoria['id']) . '">' . esc_html($categoria['nome']) . '</option>';
			}
			echo '</select></p>';
			
			echo '<p><select name="conto_id" required>';
			echo '<option value="0">Seleziona conto</option>';
			foreach ($conti as $conto) {
			    echo '<option value="' . esc_attr($conto['id']) . '">' . esc_html($conto['nome']) . '</option>';
			}
			echo '</select></p>';
			
			submit_button('Importa righe valide');
			
			echo '</form>';
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
        echo '<p><label for="data_movimento">' . esc_html__('Data movimento', 'terzo-conto') . '</label><br /><input type="date" id="data_movimento" name="data_movimento" required value="' . esc_attr((string) ($movimento['data_movimento'] ?? gmdate('Y-m-d'))) . '" /></p>';
        echo '<p><label for="importo">' . esc_html__('Importo', 'terzo-conto') . '</label><br /><input type="text" id="importo" name="importo" inputmode="decimal" required placeholder="' . esc_attr__('Es. 125,00', 'terzo-conto') . '" value="' . esc_attr((string) ($movimento['importo'] ?? '')) . '" /></p>';
        $tipo = $movimento['tipo'] ?? 'entrata';
        echo '<p><label for="tipo">' . esc_html__('Tipo movimento', 'terzo-conto') . '</label><br /><select name="tipo" id="tipo"><option value="entrata"' . selected($tipo, 'entrata', false) . '>' . esc_html__('Entrata', 'terzo-conto') . '</option><option value="uscita"' . selected($tipo, 'uscita', false) . '>' . esc_html__('Uscita', 'terzo-conto') . '</option></select></p>';

        echo '<p><label for="categoria_associazione_id">' . esc_html__('Categoria', 'terzo-conto') . '</label><br /><select name="categoria_associazione_id" id="categoria_associazione_id" required>';
        echo '<option value="0">' . esc_html__('Seleziona categoria', 'terzo-conto') . '</option>';
        $selected_categoria = (int) ($movimento['categoria_associazione_id'] ?? 0);
        $grouped_categories = [];

        foreach ($categorie as $cat) {
            $group_label = $this->get_categoria_optgroup_label(
                (string) ($cat['modello_d_tipo'] ?? ''),
                (string) ($cat['modello_d_area'] ?? '')
            );

            if (! isset($grouped_categories[$group_label])) {
                $grouped_categories[$group_label] = [];
            }

            $grouped_categories[$group_label][] = $cat;
        }

        foreach ($grouped_categories as $group_label => $group_categories) {
            echo '<optgroup label="' . esc_attr($group_label) . '">';

            foreach ($group_categories as $cat) {
                $category_code = trim((string) (($cat['modello_d_tipo'] ?? '') . ($cat['modello_d_codice'] ?? '')));
                $category_label = $category_code !== '' ? $category_code . ' - ' . $cat['nome'] : $cat['nome'];
                echo '<option value="' . esc_attr((string) $cat['id']) . '"' . selected($selected_categoria, (int) $cat['id'], false) . '>' . esc_html($category_label) . '</option>';
            }

            echo '</optgroup>';
        }
        echo '</select></p>';

        echo '<p><label for="conto_id">' . esc_html__('Conto', 'terzo-conto') . '</label><br /><select name="conto_id" id="conto_id" required>';
        echo '<option value="0">' . esc_html__('Seleziona conto', 'terzo-conto') . '</option>';
        $selected_conto = (int) ($movimento['conto_id'] ?? 0);
        foreach ($conti as $conto) {
            echo '<option value="' . esc_attr((string) $conto['id']) . '"' . selected($selected_conto, (int) $conto['id'], false) . '>' . esc_html($conto['nome']) . '</option>';
        }
        echo '</select></p>';

        echo '<p><label for="raccolta_fondi_id">' . esc_html__('Raccolta fondi', 'terzo-conto') . '</label><br /><select name="raccolta_fondi_id" id="raccolta_fondi_id"><option value="0">' . esc_html__('Nessuna raccolta', 'terzo-conto') . '</option>';
        $selected_raccolta = (int) ($movimento['raccolta_fondi_id'] ?? 0);
        foreach ($raccolte as $raccolta) {
            echo '<option value="' . esc_attr((string) $raccolta['id']) . '"' . selected($selected_raccolta, (int) $raccolta['id'], false) . '>' . esc_html($raccolta['nome']) . '</option>';
        }
        echo '</select></p>';

        echo '<p class="terzoconto-anagrafica-search"><label for="terzoconto-anagrafica-search">' . esc_html__('Soggetto / Pagatore', 'terzo-conto') . '</label><br />';
        echo '<input type="text" id="terzoconto-anagrafica-search" placeholder="' . esc_attr__('Cerca anagrafica per nome o codice fiscale', 'terzo-conto') . '" value="' . esc_attr($selected_anagrafica_label) . '" autocomplete="off" />';
        echo '<input type="hidden" name="anagrafica_id" id="anagrafica_id" value="' . esc_attr((string) $selected_anagrafica) . '" />';
        echo '<span class="terzoconto-field-help">' . esc_html__('Digita almeno 2 caratteri per cercare un’anagrafica esistente.', 'terzo-conto') . '</span>';
        echo '<div id="terzoconto-anagrafica-results" class="terzoconto-ajax-results"></div></p>';

        echo '<p style="grid-column:1/-1;"><label for="descrizione">' . esc_html__('Descrizione', 'terzo-conto') . '</label><br /><input type="text" class="large-text" id="descrizione" name="descrizione" placeholder="' . esc_attr__('Es. Donazione campagna primavera', 'terzo-conto') . '" value="' . esc_attr((string) ($movimento['descrizione'] ?? '')) . '" /></p>';
        echo '</div>';
        submit_button($is_edit ? __('Aggiorna movimento', 'terzo-conto') : __('Aggiungi movimento', 'terzo-conto'));
        echo '</form><hr />';
    }

    private function get_categoria_optgroup_label(string $tipo, string $area): string {
        $type_labels = [
            'E' => __('Entrate', 'terzo-conto'),
            'U' => __('Uscite', 'terzo-conto'),
        ];

        $area_labels = [
            'A' => __('Attività di interesse generale', 'terzo-conto'),
            'B' => __('Attività diverse', 'terzo-conto'),
            'C' => __('Attività raccolta fondi', 'terzo-conto'),
            'D' => __('Attività finanziarie/patrimoniali', 'terzo-conto'),
            'E' => __('Supporto generale', 'terzo-conto'),
        ];

        $type_label = $type_labels[$tipo] ?? __('Categorie', 'terzo-conto');
        $area_label = $area_labels[$area] ?? __('Area non specificata', 'terzo-conto');

        return sprintf('%s - %s (%s)', $type_label, $area_label, $area !== '' ? $area : '-');
    }

    private function render_movimenti_filters(string $stato_filter): void {
        echo '<form method="get" class="terzoconto-filter-form">';
        echo '<input type="hidden" name="page" value="terzoconto" />';
        echo '<label for="stato_movimento" style="margin-right:8px;">' . esc_html__('Filtra per stato', 'terzo-conto') . '</label>';
        echo '<select name="stato_movimento" id="stato_movimento">';
        echo '<option value="">' . esc_html__('Tutti gli stati', 'terzo-conto') . '</option>';
        echo '<option value="attivo"' . selected($stato_filter, 'attivo', false) . '>' . esc_html__('Attivo', 'terzo-conto') . '</option>';
        echo '<option value="annullato"' . selected($stato_filter, 'annullato', false) . '>' . esc_html__('Annullato', 'terzo-conto') . '</option>';
        echo '</select> ';
        submit_button(__('Filtra', 'terzo-conto'), 'secondary', '', false);
        echo '</form>';
    }

    private function sanitize_movimento_data(array $source): array {
        $tipo = sanitize_text_field(wp_unslash($source['tipo'] ?? 'entrata'));
        if (! in_array($tipo, ['entrata', 'uscita'], true)) {
            $tipo = 'entrata';
        }

        return [
            'data_movimento' => sanitize_text_field(wp_unslash($source['data_movimento'] ?? '')),
            'importo' => (float) str_replace(',', '.', (string) wp_unslash($source['importo'] ?? '0')),
            'tipo' => $tipo,
            'categoria_associazione_id' => absint($source['categoria_associazione_id'] ?? 0),
            'conto_id' => absint($source['conto_id'] ?? 0),
            'raccolta_fondi_id' => absint($source['raccolta_fondi_id'] ?? 0),
            'anagrafica_id' => absint($source['anagrafica_id'] ?? 0),
            'descrizione' => sanitize_text_field(wp_unslash($source['descrizione'] ?? '')),
        ];
    }

    private function validate_movimento_data(array $data, int $movimento_id = 0): bool {
        $is_valid = true;

        if ($data['data_movimento'] === '' || ! $this->is_valid_date($data['data_movimento'])) {
            add_settings_error('terzoconto', 'movimento_data_movimento', __('Inserisci una data movimento valida.', 'terzo-conto'), 'error');
            $is_valid = false;
        }

        if ($data['importo'] <= 0) {
            add_settings_error('terzoconto', 'movimento_importo', __('Inserisci un importo maggiore di zero.', 'terzo-conto'), 'error');
            $is_valid = false;
        }

        if ($data['categoria_associazione_id'] <= 0) {
            add_settings_error('terzoconto', 'movimento_categoria', __('Seleziona una categoria valida.', 'terzo-conto'), 'error');
            $is_valid = false;
        }

        if ($data['conto_id'] <= 0) {
            add_settings_error('terzoconto', 'movimento_conto', __('Seleziona un conto valido.', 'terzo-conto'), 'error');
            $is_valid = false;
        }

        $raccolta_id = (int) $data['raccolta_fondi_id'];
        if ($raccolta_id > 0 && ! $this->raccolte->is_open($raccolta_id)) {
            add_settings_error('terzoconto', 'raccolta_chiusa', __('La raccolta fondi è chiusa.', 'terzo-conto'), 'error');
            $is_valid = false;
        }

        if ($movimento_id > 0 && $data['data_movimento'] !== '') {
            $current = $this->movimenti->find_by_id($movimento_id);
            if (is_array($current)) {
                $current_year = (int) ($current['anno'] ?? 0);
                $new_year = (int) gmdate('Y', strtotime($data['data_movimento']));
                if ($current_year > 0 && $new_year !== $current_year) {
                    add_settings_error('terzoconto', 'movimento_anno', __("Non è possibile modificare l'anno di un movimento. Eliminare il movimento e crearne uno nuovo.", 'terzo-conto'), 'error');
                    $is_valid = false;
                }
            }
        }

        return $is_valid;
    }

    private function is_valid_date(string $value): bool {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
    }

    private function get_movimento_stato_filter(): string {
        $stato = sanitize_text_field(wp_unslash($_GET['stato_movimento'] ?? ''));
        return in_array($stato, ['attivo', 'annullato'], true) ? $stato : '';
    }

    private function format_anagrafica_label(array $anagrafica): string {
        if (($anagrafica['tipo'] ?? '') === 'azienda') {
            return trim((string) ($anagrafica['ragione_sociale'] ?? ''));
        }

        $label = trim((string) (($anagrafica['nome'] ?? '') . ' ' . ($anagrafica['cognome'] ?? '')));
        return $label !== '' ? $label : (string) __('Anagrafica senza nome', 'terzo-conto');
    }

    private function handle_import_preview(): void {

		if (! isset($_FILES['csv_file']) || empty($_FILES['csv_file']['tmp_name'])) {
			add_settings_error('terzoconto', 'import_missing_file', 'File mancante', 'error');
			return;
		}

		$provider = sanitize_text_field(wp_unslash($_POST['provider'] ?? 'generico'));

		$rows = $this->import_service->parse_csv($_FILES['csv_file']['tmp_name'], $provider);
echo '<pre>';
var_dump($rows);
echo '</pre>';
exit;
		if ($rows === []) {
			add_settings_error('terzoconto', 'import_empty', 'CSV vuoto', 'error');
			return;
		}

		$valid_rows = $this->import_service->get_valid_rows($rows);
		$duplicates = $this->import_service->detect_duplicates($rows, $this->movimenti->get_all());

		update_option('terzoconto_import_preview', [
			'rows' => $rows,
			'valid_rows' => $valid_rows,
			'duplicates' => $duplicates,
		]);

		add_settings_error('terzoconto', 'import_ok', 'Anteprima generata', 'updated');
	}

    private function handle_import_commit(): void {

		if (! current_user_can('manage_options')) {
			return;
		}

		check_admin_referer('terzoconto_action_nonce');

		$rows = $_POST['rows'] ?? [];

		if (empty($rows)) {
			add_settings_error('terzoconto', 'import_no_data', 'Nessun dato da importare', 'error');
			return;
		}

		$imported = 0;

		foreach ($rows as $row) {

			// salta righe senza categoria
			if (empty($row['categoria_id'])) {
				continue;
			}

			$this->movimenti->create([
				'data_movimento' => sanitize_text_field($row['data_movimento']),
				'importo' => (float) str_replace(',', '.', $row['importo']),
				'tipo' => sanitize_text_field($row['tipo']),
				'categoria_associazione_id' => (int) $row['categoria_id'],
				'conto_id' => 1, // temporaneo
				'raccolta_fondi_id' => 0,
				'anagrafica_id' => 0,
				'descrizione' => sanitize_text_field($row['descrizione']),
			]);

			$imported++;
		}

		add_settings_error('terzoconto', 'import_done', "Import completato: $imported movimenti", 'updated');
	}

    private function get_import_preview_transient_key(): string {
		return 'terzoconto_import_preview';
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
