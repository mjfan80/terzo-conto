# Risoluzione Problemi

### Errori Comuni

**1. Errore durante l'importazione CSV**
- **Sintomo:** Il plugin segnala "Numero colonne non valido".
- **Causa:** Il file CSV deve usare il punto e virgola (`;`) come separatore e codifica UTF-8.
- **Soluzione:** Esportare il file da Excel/Google Sheets assicurandosi di selezionare il formato CSV corretto.

**2. Impossibile eliminare un Conto**
- **Sintomo:** Il pulsante elimina è disabilitato.
- **Causa:** Esistono movimenti registrati su quel conto.
- **Soluzione:** Per eliminare il conto, è necessario prima spostare o eliminare i movimenti associati.

**3. Modifica dell'anno di un movimento**
- **Sintomo:** Errore "Non è possibile modificare l'anno".
- **Causa:** Per integrità del progressivo annuale, non è permesso spostare un movimento da un anno all'altro.
- **Soluzione:** Eliminare il movimento e ricrearlo con la data corretta.

### Debugging
Per problemi persistenti, abilitare il log di WordPress in `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );