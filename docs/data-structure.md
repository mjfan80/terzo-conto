# Struttura Dati

Il plugin crea 9 tabelle custom all'attivazione per garantire performance e integrità dei dati contabili.

### Tabelle Principali
- `terzoconto_movimenti`: Il cuore del sistema. Memorizza importi, date, tipi (E/U) e chiavi esterne.
- `terzoconto_categorie_modello_d`: Tabella di riferimento statica con le voci ufficiali ministeriali (es. A1, B2).
- `terzoconto_categorie_associazione`: Categorie personalizzate create dall'utente, mappate sulle voci del Modello D.
- `terzoconto_conti`: Gestione dei conti finanziari (Cassa, Banca).
- `terzoconto_raccolte_fondi`: Dati sulle raccolte occasionali e relative relazioni illustrative.
- `terzoconto_anagrafiche`: Database contatti (CF, PI, Indirizzi).
- `terzoconto_settings`: Configurazioni dell'Ente.
- `terzoconto_regole`: (Inattiva) Futura categorizzazione automatica.
- `terzoconto_comunicazioni_ae`: (Inattiva) Futura gestione invii telematici.

### Indici e Integrità
- Viene utilizzato un `progressivo_annuale` univoco per anno solare nella tabella movimenti. Gestito via codice in Movimenti_Repository::next_progressivo. Non è un campo AUTO_INCREMENT per permettere il reset ad ogni nuovo anno solare.
- La cancellazione di conti o raccolte è inibita se esistono movimenti associati (integrità referenziale gestita a livello applicativo).