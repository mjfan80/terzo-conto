# Contribuire a Terzo Conto

Grazie per l'interesse nel contribuire a Terzo Conto!

## Standard di Codifica
- Seguiamo i [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- Usa `strict_types=1` dove possibile (stiamo transizionando verso una tipizzazione forte).
- La documentazione deve essere aggiornata per ogni nuova funzionalità.

## Segnalazione Bug
1. Verifica che il bug non sia già stato segnalato.
2. Usa il template delle Issue fornito su GitHub.
3. Includi la versione di PHP e WordPress in uso.

## Pull Requests
- Crea un branch per ogni feature (`feature/nome-funzionalita`).
- Non includere modifiche al `progressivo_annuale` senza test approfonditi.
- **Cosa NON fare:** Non modificare i file vendor (Select2) direttamente; usa le dipendenze fornite.