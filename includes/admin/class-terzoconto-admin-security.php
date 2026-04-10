<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Admin_Security {

    /**
     * Verifica se l'utente ha i permessi di gestione
     */
    public function user_can_manage(): bool {
        return current_user_can('manage_options');
    }

    /**
     * Verifica capability e mostra errore se necessario
     */
    public function assert_manage_capability(): bool {
        if ($this->user_can_manage()) {
            return true;
        }

        add_settings_error(
            'terzoconto',
            'forbidden',
            __('Non autorizzato.', 'terzo-conto'),
            'error'
        );

        return false;
    }

    /**
     * Verifica nonce per richieste POST
     *
     * @param string $action Nome azione (OBBLIGATORIO, es: add_raccolta)
     * @param string $field  Nome campo nonce (default: _wpnonce)
     */
    public function verify_post_nonce(string $action, string $field = '_wpnonce'): bool {
        $nonce = $_POST[$field] ?? '';
        $nonce = is_string($nonce) ? sanitize_text_field(wp_unslash($nonce)) : '';

        if ($nonce === '' || ! wp_verify_nonce($nonce, $action)) {
            add_settings_error(
                'terzoconto',
                'invalid_nonce',
                __('Richiesta non valida, aggiorna la pagina e riprova.', 'terzo-conto'),
                'error'
            );
            return false;
        }

        return true;
    }

    /**
     * Verifica nonce per richieste GET (es. export, filtri)
     *
     * @param string $action Nome azione (es: export_csv)
     * @param string $field  Nome parametro GET (default: _wpnonce)
     */
    public function verify_get_nonce(string $action, string $field = '_wpnonce'): bool {
        $nonce = $_GET[$field] ?? '';
        $nonce = is_string($nonce) ? sanitize_text_field(wp_unslash($nonce)) : '';

        return $nonce !== '' && wp_verify_nonce($nonce, $action);
    }
}
