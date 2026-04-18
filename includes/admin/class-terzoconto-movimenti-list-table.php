<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TerzoConto_Movimenti_List_Table extends WP_List_Table {

    private TerzoConto_Movimenti_Repository $repo;

    private array $items_data = [];

	public function __construct(array $items = []) {
		parent::__construct([
			'singular' => 'movimento',
			'plural'   => 'movimenti',
			'ajax'     => false,
		]);

		$this->items_data = $items;
	}

	public function get_columns(): array {
		return [
			'cb' => '<input type="checkbox" />',
			'id' => esc_html__('ID', 'terzo-conto'),
			'stato' => esc_html__('Stato', 'terzo-conto'), 
			'data_movimento' => esc_html__('Data', 'terzo-conto'),
			'progressivo_annuale' => esc_html__('#', 'terzo-conto'),
			'tipo' => esc_html__('Tipo', 'terzo-conto'),
			'importo' => esc_html__('Importo', 'terzo-conto'),
			'conto' => esc_html__('Conto', 'terzo-conto'),
			'categoria' => esc_html__('Categoria', 'terzo-conto'),
			'raccolta' => esc_html__('Raccolta', 'terzo-conto'),
			'anagrafica' => esc_html__('Anagrafica', 'terzo-conto'),
			'descrizione' => esc_html__('Descrizione', 'terzo-conto'),
		];
	}
	
	protected function get_sortable_columns(): array {
        return [
            'id'                  => ['id', false],
            'stato'               => ['stato', false],
            'data_movimento'      => ['data_movimento', false],
            'progressivo_annuale' => ['progressivo_annuale', false],
            'tipo'                => ['tipo', false],
            'importo'             => ['importo', false],
            'conto'               => ['conto', false],
            'categoria'           => ['categoria', false],
            'raccolta'            => ['raccolta', false],
            'anagrafica'          => ['anagrafica', false],
        ];
    }

	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name="movimento_ids[]" value="%d" />',
			absint($item['id'] ?? 0)
		);
	}
	
	/* 
    RIMOSSO: Previene la generazione dei dropdown nativi di WP, dato che
    abbiamo il nostro form custom di modifica massiva.
    
    protected function get_bulk_actions() {
        return [
            'bulk_edit' => 'Modifica massiva'
        ];
    }
    */

    protected function column_default($item, $column_name) {
        return esc_html((string) ($item[$column_name] ?? ''));
    }

    protected function column_id($item) {
		$actions = [];

		// 1. MODIFICA (Sempre visibile, così puoi ripristinare un movimento annullato!)
        $edit_url = add_query_arg(
            [
                'page' => 'terzoconto',
                'edit_movimento_id' => absint($item['id'] ?? 0),
            ],
            admin_url('admin.php')
        );

        $actions['edit'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($edit_url),
            esc_html__('Modifica', 'terzo-conto')
        );

		// 2. ANNULLA (Visibile solo se il movimento è attivo)
		if (($item['stato'] ?? '') !== 'annullato') {
			$actions['annulla'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg([
							'page' => 'terzoconto',
							'terzoconto_action' => 'annulla_movimento',
							'id' => absint($item['id'] ?? 0),
						], admin_url('admin.php')),
						'terzoconto_action_nonce'
					)
				),
                esc_js(__('Confermi di voler annullare questo movimento?', 'terzo-conto')),
                esc_html__('Annulla', 'terzo-conto')
			);
		}

		return sprintf(
			'%1$s %2$s',
			esc_html((string) absint($item['id'] ?? 0)),
			$this->row_actions($actions)
		);
	}
	
	protected function column_stato($item) {
        $allowed_status_html = [
            'span' => [
                'style' => true,
            ],
        ];

        if (($item['stato'] ?? '') === 'annullato') {
            return wp_kses('<span style="background:#d63638; color:#fff; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">' . esc_html__('Annullato', 'terzo-conto') . '</span>', $allowed_status_html);
        }
        return wp_kses('<span style="background:#00a32a; color:#fff; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600;">' . esc_html__('Attivo', 'terzo-conto') . '</span>', $allowed_status_html);
    }

    public function prepare_items(): void {
		global $wpdb;

		$movimenti_table   = $wpdb->prefix . 'terzoconto_movimenti';
		$conti_table       = $wpdb->prefix . 'terzoconto_conti';
		$categorie_assoc   = $wpdb->prefix . 'terzoconto_categorie_associazione';
		$categorie_modeld  = $wpdb->prefix . 'terzoconto_categorie_modello_d';
		$raccolte_table    = $wpdb->prefix . 'terzoconto_raccolte_fondi';
		$anagrafiche_table = $wpdb->prefix . 'terzoconto_anagrafiche';

        // --- GESTIONE ORDINAMENTO (SORTING) ---
        // Mappiamo le colonne della tabella con i veri campi del database
        $allowed_orderby = [
            'id'                  => 'm.id',
            'stato'               => 'm.stato',
            'data_movimento'      => 'm.data_movimento',
            'progressivo_annuale' => 'm.progressivo_annuale',
            'tipo'                => 'm.tipo',
            'importo'             => 'm.importo',
            'conto'               => 'c.nome',
            'categoria'           => 'md.codice',
            'raccolta'            => 'r.nome',
            'anagrafica'          => 'COALESCE(a.ragione_sociale, a.cognome, a.nome)' // Ordina per azienda o cognome
        ];

        // Leggiamo i parametri dall'URL, con fallback predefinito su data_movimento
        $orderby_key = 'data_movimento';
        if (isset($_GET['orderby'])) {
            $orderby_key = sanitize_key(wp_unslash($_GET['orderby']));
        }
        // Safe: value is selected only from the static $allowed_orderby whitelist above.
        $orderby = $allowed_orderby[$orderby_key] ?? 'm.data_movimento';

        $order_key = 'DESC';
        if (isset($_GET['order'])) {
            $order_key = strtoupper(sanitize_text_field(wp_unslash($_GET['order'])));
        }
        // Safe: value is constrained to ASC/DESC only.
        $order = in_array($order_key, ['ASC', 'DESC'], true) ? $order_key : 'DESC';

        // Ordinamento secondario di sicurezza (se due date sono uguali, ordina per ID)
        // Safe: composed from allowlisted $orderby and constrained $order values.
        $fallback_order = ($orderby === 'm.data_movimento') ? ", m.id $order" : ", m.data_movimento DESC";

		// 🔥 QUERY UNICA CON ORDINAMENTO DINAMICO
		$orderby_sql  = esc_sql($orderby);
		$order_sql    = esc_sql($order);
		$fallback_sql = esc_sql($fallback_order);
		
		$sql = $wpdb->prepare(
		    "
		    SELECT 
		        m.*,
		        c.nome AS conto_nome,
		        md.tipo AS modello_tipo,
		        md.codice AS modello_codice,
		        r.nome AS raccolta_nome,
		        a.nome AS anagrafica_nome,
		        a.cognome AS anagrafica_cognome,
		        a.ragione_sociale AS anagrafica_rs,
		        a.tipo AS anagrafica_tipo
		    FROM {$movimenti_table} m
		    LEFT JOIN {$conti_table} c ON c.id = m.conto_id
		    LEFT JOIN {$categorie_assoc} ca ON ca.id = m.categoria_associazione_id
		    LEFT JOIN {$categorie_modeld} md ON md.id = ca.modello_d_id
		    LEFT JOIN {$raccolte_table} r ON r.id = m.raccolta_fondi_id
		    LEFT JOIN {$anagrafiche_table} a ON a.id = m.anagrafica_id
		    WHERE m.id >= %d
		    ORDER BY $orderby_sql $order_sql $fallback_sql
		    ",
		    0
		);
		
		$results = $wpdb->get_results($sql, ARRAY_A) ?: [];

		// 🔧 normalizzazione dati per la tabella
		foreach ($results as &$item) {
			// CONTO
			$item['conto'] = $item['conto_nome'] ?? '';

			// CATEGORIA (es: UA1)
			if (!empty($item['modello_tipo']) && !empty($item['modello_codice'])) {
				$item['categoria'] = $item['modello_tipo'] . $item['modello_codice'];
			} else {
				$item['categoria'] = '';
			}

			// RACCOLTA
			$item['raccolta'] = $item['raccolta_nome'] ?? '';

			// ANAGRAFICA
			if (!empty($item['anagrafica_tipo']) && $item['anagrafica_tipo'] === 'azienda') {
				$item['anagrafica'] = $item['anagrafica_rs'] ?? '';
			} else {
				$item['anagrafica'] = trim(
					($item['anagrafica_cognome'] ?? '') . ' ' .
					($item['anagrafica_nome'] ?? '')
				);
			}
		}

		$this->items = $results;

		$this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];

		$this->set_pagination_args([
			'total_items' => count($results),
			'per_page'    => 9999 // Mostra tutto su una pagina
		]);
	}
}
