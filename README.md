# Terzo Conto – Rendiconto ETS per WordPress

**Terzo Conto** è un sistema di gestione contabile professionale per Enti del Terzo Settore (ETS) che operano in **regime di cassa**. Il plugin automatizza la tenuta del registro cronologico e la generazione del **Rendiconto per Cassa (Modello D)** ai sensi del D.M. 5 marzo 2020.

---

## 🎯 Target Funzionale
Progettato specificamente per le Organizzazioni di Volontariato (ODV), Associazioni di Promozione Sociale (APS) e piccoli ETS che necessitano di una gestione rigorosa ma semplificata, integrata direttamente nel proprio sito WordPress (bilancio sotto i 220mila euro di entrate).

## ✨ Funzionalità Core
- **Registro Cronologico:** Gestione movimenti con calcolo automatico del progressivo annuale.
- **Reporting Ministeriale:** Generazione dinamica del Modello D (con confronto anno precedente).
- **Integrità Contabile:** Protezione contro l'eliminazione di conti o raccolte fondi con movimenti collegati.
- **Gestione Raccolte Fondi:** Rendicontazione separata e Relazione Illustrativa per raccolte occasionali (Art. 87 CTS).
- **Anagrafiche Centralizzate:** Database unico per donatori, associati e fornitori con validazione CF/PI.
- **Importazione Assistita:** Parser per CSV generici, PayPal e Satispay con anteprima e mappatura categorie.

## 🛠 Come funziona internamente (Architettura)
Il plugin adotta un'architettura a **quattro strati** per garantire manutenibilità e isolamento della logica:
1.  **UI/Admin Layer:** Gestisce l'interazione utente tramite classi Controller che sanitizzano gli input.
2.  **Service Layer:** Contiene la logica di business (es. `Movimenti_Service` valida la coerenza delle date e la chiusura delle raccolte).
3.  **Repository Layer:** Unico punto di accesso al database tramite `$wpdb`. Gestisce la persistenza sulle 9 tabelle custom del plugin.
4.  **Reporting Engine:** Motore di calcolo che aggrega i dati grezzi mappandoli sulle voci ufficiali del Modello D.

## 🔄 Flusso di un movimento (End-to-End)
1.  **Inserimento:** L'utente inserisce una spesa tramite il form admin.
2.  **Validazione:** Il sistema verifica che la data appartenga all'anno corrente e che l'eventuale raccolta fondi associata sia "Aperta".
3.  **Progressivo:** Viene calcolato il `progressivo_annuale` (es. 2024/001) basandosi sull'ultimo inserimento dell'anno solare.
4.  **Mapping:** Il movimento viene collegato a una categoria dell'associazione, la quale è a sua volta mappata su una voce del Modello D (es. Area A, Voce 2 - Servizi).
5.  **Persistenza:** Il dato viene scritto nel DB e diventa immutabile per quanto riguarda l'anno di competenza.

## ⚠️ Estendibilità e Limiti Attuali
- **Anno Fiscale:** Una volta salvato, un movimento **non può cambiare anno**. È necessario annullarlo e ricrearlo per correggere errori di data trans-annuali.
- **Competenza:** Il sistema non gestisce e non gestirà la contabilità per competenza (ratei/risconti).
- **Hooks:** Attualmente il plugin è "chiuso" per garantire la conformità fiscale. L'estendibilità tramite filtri è limitata alla Roadmap 1.1.0.

## ☕ Supporta il progetto
Sviluppato e mantenuto da **Gabriele Prandini (mjfan80)**.
- ❤️ [GitHub Sponsors](https://github.com/sponsors/mjfan80)
- ☕ [Buy Me a Coffee](https://www.buymeacoffee.com/gabrieleprandini)

---
*Licenza: GPL-2.0-or-later. Requisiti: PHP 7.4+, WP 6.0+.*