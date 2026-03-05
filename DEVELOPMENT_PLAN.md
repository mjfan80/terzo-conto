# TerzoConto – Piano di sviluppo a commit logici

## Fase 1 — Definizione schema database
**File da creare/modificare**
- `includes/class-terzoconto-activator.php`

**Contenuto**
- Definizione tabelle dedicate con `dbDelta`:
  - `wp_terzoconto_movimenti`
  - `wp_terzoconto_categorie_associazione`
  - `wp_terzoconto_categorie_modello_d`
  - `wp_terzoconto_conti`
  - `wp_terzoconto_raccolte_fondi`
  - `wp_terzoconto_regole`
- Seed categorie Modello D e conti predefiniti.
- Registrazione tassonomia attachment `terzoconto_allegato_movimento`.

**Messaggio commit consigliato**
- `feat(db): add TerzoConto ETS schema and default seeds`

## Fase 2 — Struttura plugin
**File da creare/modificare**
- `terzo-conto.php`
- `includes/class-terzoconto.php`
- `includes/admin/*`
- `includes/repositories/*`
- `includes/services/*`

**Contenuto**
- Bootstrap plugin, costanti, autoload manuale classi.
- Registrazione hook runtime/admin.

**Messaggio commit consigliato**
- `chore(plugin): scaffold TerzoConto plugin architecture`

## Fase 3 — File principale plugin
**File da creare/modificare**
- `terzo-conto.php`

**Contenuto**
- Header WordPress plugin, textdomain, activation hook.

**Messaggio commit consigliato**
- `feat(core): add main plugin entrypoint and bootstrap`

## Fase 4 — Installazione tabelle
**File da creare/modificare**
- `includes/class-terzoconto-activator.php`

**Contenuto**
- Setup schema su activation, seed iniziale conti/categorie.

**Messaggio commit consigliato**
- `feat(install): create DB tables and default reference data`

## Fase 5 — CRUD movimenti
**File da creare/modificare**
- `includes/repositories/class-terzoconto-movimenti-repository.php`
- `includes/admin/class-terzoconto-admin.php`
- `includes/admin/class-terzoconto-movimenti-list-table.php`

**Contenuto**
- Creazione/lista movimenti con numero progressivo annuale.
- Blocco inserimento su raccolte chiuse.
- Lista admin in stile `WP_List_Table`.

**Messaggio commit consigliato**
- `feat(movimenti): implement movement CRUD basics with annual progressive number`

## Fase 6 — CRUD categorie
**File da creare/modificare**
- `includes/repositories/class-terzoconto-categorie-repository.php`
- `includes/admin/class-terzoconto-admin.php`

**Contenuto**
- Lista categorie Modello D.
- Creazione categorie associazione collegate a Modello D.

**Messaggio commit consigliato**
- `feat(categorie): add association categories linked to modello d`

## Fase 7 — CRUD conti
**File da creare/modificare**
- `includes/repositories/class-terzoconto-conti-repository.php`
- `includes/admin/class-terzoconto-admin.php`

**Contenuto**
- Gestione conti estendibile (base create/list).

**Messaggio commit consigliato**
- `feat(conti): add editable payment account management`

## Fase 8 — CRUD raccolte fondi
**File da creare/modificare**
- `includes/repositories/class-terzoconto-raccolte-repository.php`
- `includes/admin/class-terzoconto-admin.php`

**Contenuto**
- Inserimento/lista raccolte fondi con stato aperta/chiusa.

**Messaggio commit consigliato**
- `feat(raccolte): add fundraising campaign management with open/closed state`

## Fase 9 — Import CSV
**File da creare/modificare**
- `includes/services/class-terzoconto-import-service.php`
- `includes/admin/class-terzoconto-admin.php`

**Contenuto**
- Upload CSV, normalizzazione provider (generico/PayPal/Satispay), anteprima e warning duplicati.

**Messaggio commit consigliato**
- `feat(import): add csv preview import flow with duplicate detection`

## Fase 10 — Export report
**File da creare/modificare**
- `includes/services/class-terzoconto-report-service.php`
- `includes/admin/class-terzoconto-admin.php`

**Contenuto**
- Bilancio annuale aggregato per categoria Modello D.
- Export CSV backup movimenti.
- Struttura pronta per estendere a Excel/PDF.

**Messaggio commit consigliato**
- `feat(report): add annual modello d summary and movement csv export`
