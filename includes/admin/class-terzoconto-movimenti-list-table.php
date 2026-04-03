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

	public function __construct(array $items) {
		parent::__construct([
			'singular' => 'movimento',
			'plural'   => 'movimenti',
			'ajax'     => false,
		]);

		$this->items_data = $items;
	}

    public function get_columns(): array {
        return [
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

    protected function column_default($item, $column_name) {
        return $item[$column_name] ?? '';
    }

    protected function column_id($item) {

        $actions = [];

        if ($item['stato'] !== 'annullato') {
            $actions['annulla'] = sprintf(
                '<form method="post" style="display:inline;">
                    %s
                    <input type="hidden" name="terzoconto_action" value="annulla_movimento">
                    <input type="hidden" name="id" value="%d">
                    <button class="button-link delete">Annulla</button>
                </form>',
                wp_nonce_field('terzoconto_action_nonce', '_wpnonce', true, false),
                $item['id']
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

		$conti = $wpdb->prefix . 'terzoconto_conti';
		$categorie = $wpdb->prefix . 'terzoconto_categorie_associazione';
		$raccolte = $wpdb->prefix . 'terzoconto_raccolte';
		$anagrafiche = $wpdb->prefix . 'terzoconto_anagrafiche';
		$items = $this->items_data;

		foreach ($items as &$item) {

			// CONTO
			$item['conto'] = $wpdb->get_var(
				$wpdb->prepare("SELECT nome FROM $conti WHERE id = %d", $item['conto_id'])
			);

			// CATEGORIA
			$categoria = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM $categorie WHERE id = %d", $item['categoria_associazione_id']),
				ARRAY_A
			);

			$cat_assoc = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT modello_d_id FROM $categorie WHERE id = %d",
					$item['categoria_associazione_id']
				),
				ARRAY_A
			);

			if ($cat_assoc && !empty($cat_assoc['modello_d_id'])) {

				$modello = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT tipo, codice FROM {$wpdb->prefix}terzoconto_categorie_modello_d WHERE id = %d",
						$cat_assoc['modello_d_id']
					),
					ARRAY_A
				);

				if ($modello) {
					$item['categoria'] = $modello['tipo'] . $modello['codice']; // es: UA1
				} else {
					$item['categoria'] = '';
				}

			} else {
				$item['categoria'] = '';
			}

			// RACCOLTA
			if (!empty($item['raccolta_fondi_id'])) {
				$item['raccolta'] = $wpdb->get_var(
					$wpdb->prepare("SELECT nome FROM $raccolte WHERE id = %d", $item['raccolta_fondi_id'])
				);
			} else {
				$item['raccolta'] = '';
			}

			// ANAGRAFICA
			if (!empty($item['anagrafica_id'])) {
				$anagrafica = $wpdb->get_row(
					$wpdb->prepare("SELECT nome, cognome FROM $anagrafiche WHERE id = %d", $item['anagrafica_id']),
					ARRAY_A
				);

				$item['anagrafica'] = $anagrafica
					? ($anagrafica['cognome'] . ' ' . $anagrafica['nome'])
					: '';
			} else {
				$item['anagrafica'] = '';
			}
		}

		$this->items = $items;

		$this->_column_headers = [$this->get_columns(), [], []];
	}
}
