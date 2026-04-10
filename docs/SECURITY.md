# Policy di Sicurezza

## Segnalazione Vulnerabilità
Se scopri una vulnerabilità di sicurezza, ti preghiamo di non aprire una issue pubblica. Invia un'email a [indirizzo-email-placeholder] o utilizza il sistema di segnalazione privata di GitHub.

## Best Practice
- Il plugin utilizza `check_admin_referer` per ogni azione POST.
- L'accesso è limitato alla capability `manage_options`.
- Tutti gli output sono sottoposti a `esc_html`, `esc_attr` o `wp_kses_post`.