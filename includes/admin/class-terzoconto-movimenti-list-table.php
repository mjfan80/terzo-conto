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

        if ($column_name === 'id') {
            $edit_url = add_query_arg([
                'page' => 'terzoconto',
                'edit_movimento_id' => (int) $item['id'],
            ], admin_url('admin.php'));
            $output = '<a href="' . esc_url($edit_url) . '">' . esc_html((string) $item['id']) . '</a>';

            if (($item['stato'] ?? '') !== 'annullato') {
                $output .= '<form method="post" style="display:inline-block;margin-left:8px;">';
                $output .= wp_nonce_field('terzoconto_action_nonce', '_wpnonce', true, false);
                $output .= '<input type="hidden" name="terzoconto_action" value="annulla_movimento" />';
                $output .= '<input type="hidden" name="id" value="' . esc_attr((string) $item['id']) . '" />';
                $output .= '<button type="submit" class="button-link-delete" onclick="return confirm(\'' . esc_js(__('Vuoi davvero annullare questo movimento?', 'terzo-conto')) . '\');">' . esc_html__('Annulla movimento', 'terzo-conto') . '</button>';
                $output .= '</form>';
            }

            return $output;
        }

        return esc_html((string) ($item[$column_name] ?? ''));
    }
}
