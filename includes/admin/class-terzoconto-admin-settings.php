<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Admin_Settings {
    public function __construct(private TerzoConto_Settings_Repository $settings_repository) {
    }

    public function hooks(): void {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'handle_post_actions']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function register_menu(): void {
        add_submenu_page(
            'terzoconto',
            __('Impostazioni', 'terzo-conto'),
            __('Impostazioni', 'terzo-conto'),
            'manage_options',
            'terzoconto-impostazioni',
            [$this, 'render_page']
        );
    }

    public function handle_post_actions(): void {
        if (! current_user_can('manage_options') || ! isset($_POST['terzoconto_settings_action'])) {
            return;
        }

        check_admin_referer('terzoconto_settings_nonce');

        $action = sanitize_text_field(wp_unslash($_POST['terzoconto_settings_action']));
        if ($action !== 'save_settings') {
            return;
        }

        $saved = $this->settings_repository->save([
            'nome_ente' => sanitize_text_field(wp_unslash($_POST['nome_ente'] ?? '')),
            'codice_fiscale' => strtoupper(sanitize_text_field(wp_unslash($_POST['codice_fiscale'] ?? ''))),
            'partita_iva' => sanitize_text_field(wp_unslash($_POST['partita_iva'] ?? '')),
            'numero_runts' => sanitize_text_field(wp_unslash($_POST['numero_runts'] ?? '')),
            'indirizzo' => sanitize_text_field(wp_unslash($_POST['indirizzo'] ?? '')),
            'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
            'telefono' => sanitize_text_field(wp_unslash($_POST['telefono'] ?? '')),
            'logo_url' => esc_url_raw(wp_unslash($_POST['logo_url'] ?? '')),
        ]);

        $status = $saved ? 'saved' : 'error';
        wp_safe_redirect(add_query_arg('tc_settings_status', $status, admin_url('admin.php?page=terzoconto-impostazioni')));
        exit;
    }

    public function enqueue_assets(string $hook): void {
        if ($hook !== 'terzoconto_page_terzoconto-impostazioni') {
            return;
        }

        wp_enqueue_media();
        wp_add_inline_script('jquery-core', "jQuery(function($){
            var frame;
            $(document).on('click', '#terzoconto-logo-upload', function(e){
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({title: '" . esc_js(__('Seleziona logo', 'terzo-conto')) . "', button: {text: '" . esc_js(__('Usa questo logo', 'terzo-conto')) . "'}, multiple: false});
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#logo_url').val(attachment.url);
                });
                frame.open();
            });
        });");
    }

    public function render_page(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Non autorizzato.', 'terzo-conto'));
        }

        $settings = $this->settings_repository->get() ?: [];

        echo '<div class="wrap"><h1>' . esc_html__('Impostazioni associazione', 'terzo-conto') . '</h1>';
        $this->render_notice();
        echo '<form method="post">';
        wp_nonce_field('terzoconto_settings_nonce');
        echo '<input type="hidden" name="terzoconto_settings_action" value="save_settings" />';

        echo '<table class="form-table" role="presentation"><tbody>';
        $this->render_input('nome_ente', __('Nome ente', 'terzo-conto'), $settings['nome_ente'] ?? '');
        $this->render_input('codice_fiscale', __('Codice fiscale', 'terzo-conto'), $settings['codice_fiscale'] ?? '');
        $this->render_input('partita_iva', __('Partita IVA', 'terzo-conto'), $settings['partita_iva'] ?? '');
        $this->render_input('numero_runts', __('Numero RUNTS', 'terzo-conto'), $settings['numero_runts'] ?? '');
        $this->render_input('indirizzo', __('Indirizzo', 'terzo-conto'), $settings['indirizzo'] ?? '');
        $this->render_input('email', __('Email', 'terzo-conto'), $settings['email'] ?? '', 'email');
        $this->render_input('telefono', __('Telefono', 'terzo-conto'), $settings['telefono'] ?? '');

        echo '<tr><th scope="row"><label for="logo_url">' . esc_html__('Logo URL', 'terzo-conto') . '</label></th><td>';
        echo '<input type="url" class="regular-text" id="logo_url" name="logo_url" value="' . esc_attr((string) ($settings['logo_url'] ?? '')) . '" /> ';
        echo '<button id="terzoconto-logo-upload" class="button" type="button">' . esc_html__('Seleziona dalla libreria media', 'terzo-conto') . '</button>';
        if (! empty($settings['logo_url'])) {
            echo '<p><img src="' . esc_url($settings['logo_url']) . '" alt="" style="max-width:120px;height:auto;" /></p>';
        }
        echo '</td></tr>';
        echo '</tbody></table>';

        submit_button(__('Salva impostazioni', 'terzo-conto'));
        echo '</form></div>';
    }

    private function render_input(string $name, string $label, string $value, string $type = 'text'): void {
        echo '<tr><th scope="row"><label for="' . esc_attr($name) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="' . esc_attr($type) . '" class="regular-text" id="' . esc_attr($name) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
        echo '</td></tr>';
    }

    private function render_notice(): void {
        $status = sanitize_text_field(wp_unslash($_GET['tc_settings_status'] ?? ''));
        if ($status === 'saved') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Impostazioni salvate.', 'terzo-conto') . '</p></div>';
        } elseif ($status === 'error') {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Errore durante il salvataggio.', 'terzo-conto') . '</p></div>';
        }
    }
}
