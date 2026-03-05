<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class TerzoConto_Movimenti_List_Table extends WP_List_Table {
    private array $items_data;

    public function __construct(array $items_data) {
        parent::__construct([
            'singular' => 'movimento',
            'plural' => 'movimenti',
            'ajax' => false,
        ]);
        $this->items_data = $items_data;
    }

    public function get_columns(): array {
        return [
            'id' => __('ID', 'terzo-conto'),
            'data_movimento' => __('Data', 'terzo-conto'),
            'progressivo_annuale' => __('#', 'terzo-conto'),
            'tipo' => __('Tipo', 'terzo-conto'),
            'importo' => __('Importo', 'terzo-conto'),
            'descrizione' => __('Descrizione', 'terzo-conto'),
            'stato' => __('Stato', 'terzo-conto'),
        ];
    }

    public function prepare_items(): void {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];

        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items = $this->items_data;
    }

    protected function column_default($item, $column_name) {
        if ($column_name === 'importo') {
            return esc_html(number_format((float) $item[$column_name], 2, ',', '.'));
        }
        return esc_html((string) ($item[$column_name] ?? ''));
    }
}
