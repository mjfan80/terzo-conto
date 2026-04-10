# WordPress Hooks

### Hooks Utilizzati (Core)
- `admin_menu`: Registrazione delle pagine di gestione.
- `admin_init`: Gestione delle azioni POST e salvataggio dati.
- `admin_enqueue_scripts`: Caricamento asset (Select2, Media Uploader).
- `wp_ajax_terzoconto_search_anagrafiche`: Ricerca asincrona per il campo anagrafica nei movimenti.
- `plugins_loaded`: Bootstrap del plugin e controllo versione DB.

### Custom Taxonomy
- `terzoconto_allegato_movimento`: Utilizzata per mappare file della Libreria Media ai movimenti contabili (registrata internamente su `attachment`).

### Filtri e Azioni per Sviluppatori
*Nota: Attualmente il plugin non espone apply_filters nelle query del Repository per evitare che plugin esterni alterino la consistenza del Modello D. Gli hook sono prevalentemente d'azione (admin_init, wp_ajax).*