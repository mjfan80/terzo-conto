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
			'id' => 'ID',
			'data_movimento' => 'Data',
			'progressivo_annuale' => '#',
			'tipo' => 'Tipo',
			'importo' => 'Importo',
			'conto' => 'Conto',
			'categoria' => 'Categoria',
			'raccolta' => 'Raccolta',
			'anagrafica' => 'Anagrafica',
			'descrizione' => 'Descrizione',
		];
	}

	protected function column_cb($item) {
		return sprintf(
			'<input type="checkbox" name="movimento_ids[]" value="%d" />',
			$item['id']
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
        return $item[$column_name] ?? '';
    }

    protected function column_id($item) {

		$actions = [];

		// MODIFICA
		if ($item['stato'] !== 'annullato') {
			$edit_url = add_query_arg(
				[
					'page' => 'terzoconto',
					'edit_movimento_id' => $item['id'],
				],
				admin_url('admin.php')
			);

			$actions['edit'] = sprintf(
				'<a href="%s">Modifica</a>',
				esc_url($edit_url)
			);
		}

		// ANNULLA
		if ($item['stato'] !== 'annullato') {
			$actions['annulla'] = sprintf(
				'<a href="%s" class="submitdelete" onclick="return confirm(\'Confermi?\')">Annulla</a>',
				esc_url(
					wp_nonce_url(
						add_query_arg([
							'page' => 'terzoconto',
							'terzoconto_action' => 'annulla_movimento',
							'id' => $item['id'],
						], admin_url('admin.php')),
						'terzoconto_action_nonce'
					)
				)
			);
		}

		return sprintf(
			'%1$s %2$s',
			$item['id'],
			$this->row_actions($actions)
		);
	}

    public function prepare_items(): void {

		global $wpdb;

		$movimenti_table   = $wpdb->prefix . 'terzoconto_movimenti';
		$conti_table       = $wpdb->prefix . 'terzoconto_conti';
		$categorie_assoc   = $wpdb->prefix . 'terzoconto_categorie_associazione';
		$categorie_modeld  = $wpdb->prefix . 'terzoconto_categorie_modello_d';
		$raccolte_table    = $wpdb->prefix . 'terzoconto_raccolte_fondi';
		$anagrafiche_table = $wpdb->prefix . 'terzoconto_anagrafiche';

		// 🔥 QUERY UNICA (niente più N+1)
		$results = $wpdb->get_results("
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

			FROM $movimenti_table m

			LEFT JOIN $conti_table c 
				ON c.id = m.conto_id

			LEFT JOIN $categorie_assoc ca 
				ON ca.id = m.categoria_associazione_id

			LEFT JOIN $categorie_modeld md 
				ON md.id = ca.modello_d_id

			LEFT JOIN $raccolte_table r 
				ON r.id = m.raccolta_fondi_id

			LEFT JOIN $anagrafiche_table a 
				ON a.id = m.anagrafica_id

			ORDER BY m.data_movimento DESC, m.id DESC
		", ARRAY_A) ?: [];

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

		$this->_column_headers = [$this->get_columns(), [], []];

		$this->set_pagination_args([
			'total_items' => count($results),
			'per_page'    => 9999
		]);
	}
}
