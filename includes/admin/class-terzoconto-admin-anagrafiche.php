<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Admin_Anagrafiche {
    private ?array $submitted_anagrafica = null;

    public function __construct(private TerzoConto_Anagrafiche_Repository $anagrafiche_repository) {
    }

    public function hooks(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'handle_post_actions']);
        add_action('wp_ajax_terzoconto_search_anagrafiche', [$this, 'ajax_search_anagrafiche']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'terzoconto',
            __('Anagrafiche', 'terzoconto-rendiconto-ets'),
            __('Anagrafiche', 'terzoconto-rendiconto-ets'),
            'manage_options',
            'terzoconto-anagrafiche',
            [$this, 'render_page']
        );
    }

    public function handle_post_actions(): void {
        if (! current_user_can('manage_options') || ! isset($_POST['terzoconto_anagrafica_action'])) {
            return;
        }

        check_admin_referer('terzoconto_anagrafica_action_nonce');

        $action = sanitize_text_field(wp_unslash($_POST['terzoconto_anagrafica_action']));
        $data = $this->sanitize_anagrafica_data($_POST);
        $this->submitted_anagrafica = $data;

        if ($action === 'update_anagrafica') {
            $submitted_id = absint(wp_unslash($_POST['id'] ?? 0));
            if ($submitted_id > 0) {
                $this->submitted_anagrafica['id'] = $submitted_id;
            }
        }

        if (! $this->validate_anagrafica_data($data)) {
            return;
        }

        if ($action === 'create_anagrafica') {
            $created = $this->anagrafiche_repository->create($data);
            $status = $created > 0 ? 'created' : 'error';
            wp_safe_redirect(add_query_arg('tc_anagrafica_status', $status, admin_url('admin.php?page=terzoconto-anagrafiche')));
            exit;
        }

        if ($action === 'update_anagrafica') {
            $id = absint(wp_unslash($_POST['id'] ?? 0));
            $updated = $id > 0 ? $this->anagrafiche_repository->update($id, $data) : false;
            $status = $updated ? 'updated' : 'error';
            wp_safe_redirect(add_query_arg('tc_anagrafica_status', $status, admin_url('admin.php?page=terzoconto-anagrafiche')));
            exit;
        }
    }

    public function render_page(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets'));
        }

        $edit_id = absint(wp_unslash($_GET['edit_id'] ?? 0));
        $anagrafica = $edit_id > 0 ? $this->anagrafiche_repository->find_by_id($edit_id) : null;
        if (is_array($this->submitted_anagrafica)) {
            $anagrafica = $this->submitted_anagrafica;
        }
        $rows = $this->anagrafiche_repository->search('');

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Anagrafiche', 'terzoconto-rendiconto-ets') . '</h1>';

        $this->render_notice();
        settings_errors('terzoconto_anagrafiche');
        $this->render_form($anagrafica);
        $this->render_table($rows);

        echo '</div>';
    }

    public function ajax_search_anagrafiche(): void {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Non autorizzato.', 'terzoconto-rendiconto-ets')], 403);
        }

        check_ajax_referer('terzoconto_search_anagrafiche_nonce', 'nonce');

        $term = sanitize_text_field(wp_unslash($_GET['term'] ?? ''));
        $results = $this->anagrafiche_repository->search($term);

        $response = [];
        foreach ($results as $row) {
            $response[] = [
                'id' => (int) $row['id'],
                'label' => $this->build_label($row),
                'codice_fiscale' => (string) ($row['codice_fiscale'] ?? ''),
            ];
        }

        wp_send_json($response);
    }

    private function render_notice(): void {
        $status = sanitize_text_field(wp_unslash($_GET['tc_anagrafica_status'] ?? ''));

        if ($status === 'created') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Anagrafica creata con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'updated') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Anagrafica aggiornata con successo.', 'terzoconto-rendiconto-ets') . '</p></div>';
        } elseif ($status === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Operazione non riuscita.', 'terzoconto-rendiconto-ets') . '</p></div>';
        }
    }

    private function render_form(?array $anagrafica): void {
        $is_edit = is_array($anagrafica);

        echo '<h2>' . esc_html($is_edit ? __('Modifica anagrafica', 'terzoconto-rendiconto-ets') : __('Nuova anagrafica', 'terzoconto-rendiconto-ets')) . '</h2>';
        echo '<form method="post">';
        wp_nonce_field('terzoconto_anagrafica_action_nonce');

        echo '<input type="hidden" name="terzoconto_anagrafica_action" value="' . esc_attr($is_edit ? 'update_anagrafica' : 'create_anagrafica') . '" />';
        if ($is_edit) {
            echo '<input type="hidden" name="id" value="' . esc_attr((string) $anagrafica['id']) . '" />';
        }

        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="tipo">' . esc_html__('Tipo', 'terzoconto-rendiconto-ets') . '</label></th><td>';
        echo '<select id="tipo" name="tipo">';
        $selected_tipo = $anagrafica['tipo'] ?? 'persona';
        echo '<option value="persona"' . selected($selected_tipo, 'persona', false) . '>' . esc_html__('Persona', 'terzoconto-rendiconto-ets') . '</option>';
        echo '<option value="azienda"' . selected($selected_tipo, 'azienda', false) . '>' . esc_html__('Azienda', 'terzoconto-rendiconto-ets') . '</option>';
        echo '</select></td></tr>';

        $this->render_input_row('nome', __('Nome', 'terzoconto-rendiconto-ets'), $anagrafica['nome'] ?? '');
        $this->render_input_row('cognome', __('Cognome', 'terzoconto-rendiconto-ets'), $anagrafica['cognome'] ?? '');
        $this->render_input_row('ragione_sociale', __('Ragione sociale', 'terzoconto-rendiconto-ets'), $anagrafica['ragione_sociale'] ?? '');
        $this->render_input_row('codice_fiscale', __('Codice fiscale', 'terzoconto-rendiconto-ets'), $anagrafica['codice_fiscale'] ?? '');
        $this->render_input_row('email', __('Email', 'terzoconto-rendiconto-ets'), $anagrafica['email'] ?? '', 'email');
        $this->render_input_row('telefono', __('Telefono', 'terzoconto-rendiconto-ets'), $anagrafica['telefono'] ?? '');

        echo '</tbody></table>';
        submit_button($is_edit ? __('Aggiorna anagrafica', 'terzoconto-rendiconto-ets') : __('Aggiungi anagrafica', 'terzoconto-rendiconto-ets'));
        echo '</form><hr />';
    }

    private function render_input_row(string $name, string $label, string $value, string $type = 'text'): void {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="' . esc_attr($type) . '" class="regular-text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        echo '</td></tr>';
    }

    private function render_table(array $rows): void {
        echo '<h2>' . esc_html__('Elenco anagrafiche', 'terzoconto-rendiconto-ets') . '</h2>';
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Nome', 'terzoconto-rendiconto-ets') . '</th>';
        echo '<th>' . esc_html__('Codice fiscale', 'terzoconto-rendiconto-ets') . '</th>';
        echo '<th>' . esc_html__('Email', 'terzoconto-rendiconto-ets') . '</th>';
        echo '<th>' . esc_html__('Telefono', 'terzoconto-rendiconto-ets') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($rows)) {
            echo '<tr><td colspan="4">' . esc_html__('Nessuna anagrafica presente.', 'terzoconto-rendiconto-ets') . '</td></tr>';
        } else {
            foreach ($rows as $row) {
                $name = $this->build_label($row);
                $edit_url = add_query_arg(
                    [
                        'page' => 'terzoconto-anagrafiche',
                        'edit_id' => (int) $row['id'],
                    ],
                    admin_url('admin.php')
                );

                echo '<tr>';
                echo '<td><a href="' . esc_url($edit_url) . '">' . esc_html($name) . '</a></td>';
                echo '<td>' . esc_html((string) ($row['codice_fiscale'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['email'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['telefono'] ?? '')) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private function sanitize_anagrafica_data(array $source): array {
        $tipo = sanitize_text_field(wp_unslash($source['tipo'] ?? 'persona'));
        if (! in_array($tipo, ['persona', 'azienda'], true)) {
            $tipo = 'persona';
        }

        return [
            'tipo' => $tipo,
            'nome' => sanitize_text_field(wp_unslash($source['nome'] ?? '')),
            'cognome' => sanitize_text_field(wp_unslash($source['cognome'] ?? '')),
            'ragione_sociale' => sanitize_text_field(wp_unslash($source['ragione_sociale'] ?? '')),
            'codice_fiscale' => strtoupper(sanitize_text_field(wp_unslash($source['codice_fiscale'] ?? ''))),
            'email' => sanitize_email(wp_unslash($source['email'] ?? '')),
            'telefono' => sanitize_text_field(wp_unslash($source['telefono'] ?? '')),
        ];
    }

    private function validate_anagrafica_data(array $data): bool {
        $is_valid = true;

        if ($data['tipo'] === 'azienda') {
            if ($data['ragione_sociale'] === '') {
                add_settings_error('terzoconto_anagrafiche', 'anagrafica_ragione_sociale', __('Per le aziende la ragione sociale è obbligatoria.', 'terzoconto-rendiconto-ets'), 'error');
                $is_valid = false;
            }
        } else {
            if ($data['nome'] === '') {
                add_settings_error('terzoconto_anagrafiche', 'anagrafica_nome', __('Per le persone il nome è obbligatorio.', 'terzoconto-rendiconto-ets'), 'error');
                $is_valid = false;
            }

            if ($data['cognome'] === '') {
                add_settings_error('terzoconto_anagrafiche', 'anagrafica_cognome', __('Per le persone il cognome è obbligatorio.', 'terzoconto-rendiconto-ets'), 'error');
                $is_valid = false;
            }
        }

        if ($data['email'] !== '' && ! is_email($data['email'])) {
            add_settings_error('terzoconto_anagrafiche', 'anagrafica_email', __('Inserisci un indirizzo email valido.', 'terzoconto-rendiconto-ets'), 'error');
            $is_valid = false;
        }

        if ($data['codice_fiscale'] === '') {
            add_settings_error('terzoconto_anagrafiche', 'anagrafica_codice_fiscale', __('Il codice fiscale è obbligatorio.', 'terzoconto-rendiconto-ets'), 'error');
            $is_valid = false;
        }

        return $is_valid;
    }

    private function build_label(array $row): string {
        $nome = trim((string) ($row['nome'] ?? ''));
        $cognome = trim((string) ($row['cognome'] ?? ''));
        $ragione_sociale = trim((string) ($row['ragione_sociale'] ?? ''));

        $label = trim($nome . ' ' . $cognome);
        if ($label === '') {
            $label = $ragione_sociale;
        }

        if ($label === '') {
            $label = (string) __('Anagrafica senza nome', 'terzoconto-rendiconto-ets');
        }

        return $label;
    }
}
