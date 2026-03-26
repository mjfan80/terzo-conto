<?php

if (! defined('ABSPATH')) {
    exit;
}

class TerzoConto_Admin_Security {
    public function user_can_manage(): bool {
        return current_user_can('manage_options');
    }

    public function assert_manage_capability(): bool {
        if ($this->user_can_manage()) {
            return true;
        }

        add_settings_error('terzoconto', 'forbidden', __('Non autorizzato.', 'terzo-conto'), 'error');
        return false;
    }

    public function verify_action_nonce(string $field = '_wpnonce', string $action = 'terzoconto_action_nonce'): bool {
        $nonce = sanitize_text_field(wp_unslash($_POST[$field] ?? ''));

        if ($nonce === '' || ! wp_verify_nonce($nonce, $action)) {
            add_settings_error('terzoconto', 'invalid_nonce', __('Richiesta non valida, aggiorna la pagina e riprova.', 'terzo-conto'), 'error');
            return false;
        }

        return true;
    }

    public function verify_get_nonce(string $nonce, string $action): bool {
        return $nonce !== '' && wp_verify_nonce($nonce, $action) !== false;
    }
}
