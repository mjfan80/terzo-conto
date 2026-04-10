# Architettura Tecnica

Il plugin segue un pattern orientato agli oggetti, separando la logica di accesso ai dati dalla logica di presentazione.

### Componenti Core
1. **Bootstrapping (`TerzoConto`)**: Inizializza i componenti e carica i menu admin.
2. **Repositories**: Classi dedicate esclusivamente alle query SQL. Utilizzano `$wpdb` per interagire con tabelle custom.
   - Esempio: `TerzoConto_Movimenti_Repository`.
3. **Services**: Classi che contengono la logica di business complessa.
   - `Import_Service`: Gestisce la logica di parsing e validazione CSV.
   - `Report_Service`: Calcola i totali aggregati per i report fiscali.
4. **Admin Controllers**: Gestiscono i form, la sanificazione degli input (`POST`) e il rendering dei template.

### Punti di Estensione: 
L'architettura prevede l'estensione tramite la classe TerzoConto_Import_Service. Gli sviluppatori possono iniettare nuovi provider di parsing implementando la logica di normalizzazione nel metodo normalize_row.

### Flusso dei Dati
Input Utente (Admin Form) -> Admin Controller (Sanitizzazione) -> Service (Validazione/Business Logic) -> Repository (Persistenza DB).

### Integrazione con WordPress
- Hook `admin_menu` per registrazione pagine
- Hook `admin_init` per gestione POST
- Uso di `$wpdb` per accesso DB
- AJAX via `wp_ajax_*`