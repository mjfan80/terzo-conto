# TerzoConto – Architettura del sistema

## Scopo del plugin

TerzoConto è un plugin WordPress per la gestione del **rendiconto per cassa (Modello D)** degli enti del Terzo Settore italiani (APS, ODV, ETS sotto soglia).

Il plugin non è un sistema di contabilità generale.

È progettato come:

* registro dei movimenti economici
* sistema di classificazione secondo il Modello D
* generatore automatico di report di bilancio.

Il principio fondamentale è:

**tutti i dati derivano dal registro movimenti.**

---

# Architettura generale

Il plugin segue una struttura modulare divisa in quattro livelli:

1. bootstrap plugin
2. repository database
3. servizi applicativi
4. interfaccia admin WordPress

Struttura directory:

```
terzo-conto/
├ terzo-conto.php
├ includes/
│
├ admin/
│
├ repositories/
│
├ services/
│
└ templates/
```

---

# Modello dati

Il sistema è costruito attorno alla tabella:

```
movimenti
```

Ogni operazione economica è registrata come movimento.

I report sono generati aggregando i movimenti.

Relazioni principali:

```
movimenti
 ├ categoria_associazione
 ├ conto
 └ raccolta_fondi
```

---

# Tabelle principali

## movimenti

Registro delle operazioni economiche.

Campi principali:

* data
* importo
* tipo (E = entrata, U = uscita)
* categoria_associazione_id
* conto_id
* raccolta_fondi_id
* descrizione
* utente_id
* stato
* numero_progressivo_annuale

Gli allegati ai movimenti sono gestiti tramite Media Library WordPress.

---

## categorie_modello_d

Contiene le categorie ufficiali de
