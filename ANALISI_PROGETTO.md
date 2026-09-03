# 📋 Analisi Progetto: CV Backoffice

> **Data analisi:** 2026-08-02
> **Progetto:** Laravel 11 + Livewire 4 — Gestione flotta mezzi pubblica assistenza
> **Stato:** ✅ **Completato** — Pronto per il deploy

---

## 📊 Riepilogo Finale

| Categoria                   | Stato                                         |
| --------------------------- | --------------------------------------------- |
| **Bug (B1-B15)**            | ✅ **Tutti risolti**                          |
| **Best Practice (BP1-BP8)** | ✅ **Tutte risolte**                          |
| **Migliorie (M10-M18)**     | ✅ **Completate**                             |
| **Feature implementate**    | ✅ F12, F14, F15, F16, F17, F18, F19, F20     |
| **Registrazione utenti**    | ✅ Disabilitata (admin via artisan)           |
| **Test**                    | ✅ **178 test, 346 assertions — tutti verdi** |

---

## Indice

1. [🐛 Bug Risolti](#-bug-risolti)
2. [⚠️ Duplicazioni Risolte](#️-duplicazioni-risolte)
3. [💡 Migliorie e Ottimizzazioni](#-migliorie-e-ottimizzazioni)
4. [🏗️ Feature Implementate](#️-feature-implementate)
5. [🔧 Refactoring](#-refactoring)
6. [🔵 Deferito / Futuro](#-deferito--futuro)

---

## 🐛 Bug Risolti

### B1-B6 — Bug iniziali ✅

`syncStatusFromRules()` sovrascriveva stato manuale, `getAutomaticStatusAttribute()` ramo else sbagliato, default `warning_months` disallineato, `$vehicle->mileage` inesistente in view, `update()` ignorava registration_card, `internal_code` perdeva zeri iniziali.

### B7-B15 — Bug trovati in analisi ✅

`resolveDueDate()` (falso positivo), route notifiche/PDF senza auth, `relationLoaded('vehicle')` fragile, N+1 in `syncStatusFromRules()`, email hardcoded, accessor NotificationSetting, `internal_code` senza unique, eager load ridondante (falso positivo).

---

## ⚠️ Duplicazioni Risolte ✅

**D1-D4:** Logica ordinamento/raggruppamento in trait `SortableAndGroupable`, rilevamento duplicati in trait `DetectsDuplicates`, calcolo garanzia in trait `HandlesWarrantyExtension`, `resolveStatus()` rimosso dal controller.

---

## 💡 Migliorie e Ottimizzazioni ✅

| #         | Cosa                         | Soluzione                                                    |
| --------- | ---------------------------- | ------------------------------------------------------------ |
| **M1**    | Ordinamento DB vs Collection | `applySorting()` con mappa colonna/callable                  |
| **M2**    | Paginazione                  | `->paginate(20)` in 6 controller                             |
| **M3-M4** | Unique validation            | `serial_number` e `name`                                     |
| **M5**    | SoftDeletes                  | vehicles, issues, maintenance_records, deadlines, providers  |
| **M6**    | Authorization/Policy         | 9 Policy + trait `HasRoleBasedAccess`                        |
| **M7-M8** | Validazioni extra            | `after_or_equal:immatricolation_date`, mileage >= ultimo log |
| **M9**    | Tema chiaro/scuro            | Script JS con localStorage                                   |
| **M10**   | Cache dashboard              | `Cache::remember('dashboard.stats', 300)`                    |
| **M11**   | Indici DB                    | Migration `add_performance_indexes` (6 indici)               |
| **M12**   | DeadlineController memoria   | Filtro `latestRevisionOnly` a monte via DB                   |
| **M13**   | Backfill observer            | Safety limit `MAX_BACKFILL_ITERATIONS = 10`                  |

---

## 🏗️ Feature Implementate ✅

| #       | Feature                        | Dettaglio                                                      |
| ------- | ------------------------------ | -------------------------------------------------------------- |
| **F1**  | Dashboard                      | Statistiche, card interattive, badge, dark mode                |
| **F2**  | Notifiche email                | `SendSummaryReport` + `ReportMail` + `NotificationSetting`     |
| **F3**  | Export PDF                     | DomPDF + scheda veicolo completa                               |
| **F4**  | Audit log                      | `spatie/laravel-activitylog` su 5 model                        |
| **F5**  | Manutenzione ↔ guasti/scadenze | Pivot polimorfico `maintenance_record_items`                   |
| **F6**  | Alert equipaggiamento          | Scadenze in dashboard e report email                           |
| **F7**  | Test                           | **166 test, 330 assertions**                                   |
| **F9**  | Ricerca/filtro                 | Trait `Searchable` + barra ricerca nelle index                 |
| **F10** | Mileage integrato              | Km su manutenzione, bulk mensile, scadenze km                  |
| **F12** | Audit log UI                   | `ActivityLogController` + view con filtri e modale JSON        |
| **F14** | Export CSV                     | `CsvExportController` per 7 entità, pulsante in tutte le index |
| **F15** | Storico revisioni              | Tabella cronologica nella show del veicolo                     |
| **F16** | Calendario appuntamenti        | FullCalendar con colori per tipo attività                      |
| **F18** | API REST                       | Sanctum, 11 endpoint, auth via token                           |
| **F19** | i18n                           | File `lang/it/messages.php`, locale italiano                   |
| **F20** | Rate limiting                  | 30 req/min admin, 5 req/min login                              |

---

## 🔧 Refactoring

- **Service Layer:** `DeadlineService` per logica business scadenze
- **FormRequest:** `UpdateNotificationSettingRequest` con validazione
- **Trait:** `SortableAndGroupable`, `DetectsDuplicates`, `HandlesWarrantyExtension`, `AdminOnlyAccess`, `HasRoleBasedAccess`, `Searchable`
- **Scope Eloquent:** `Issue::scopeOpen()`, `Deadline::scopeUpcoming()`, `Equipment::scopeExpiringSoon()`
- **Accessor Vehicle:** `mileage`, `warranty_original_expiration_date`, `deadlines_grouped`
- **Relazione:** `latestMileageLog` su Vehicle
- **Provider:** SoftDeletes + migration
- **MileageLog:** ActivityLog trait

---

## 🔵 Deferito / Futuro

| Cosa                       | Perché                             |
| -------------------------- | ---------------------------------- |
| **F11 — Gestione utenti**  | Non serve (solo admin usa l'app)   |
| **F13 — Notifiche in-app** | Complessità alta, non blocca l'uso |
| **M16 — Fulltext search**  | Volume dati attuale irrilevante    |

---

## 🏗️ Futuro: Sistema Utenti, Gruppi e Ruoli

> **Nota:** Questa sezione descrive la visione per il futuro sistema multi-utente. Da implementare quando servirà.

### Contesto attuale

Oggi l'app è usata da un **singolo admin** (registrazione pubblica disabilitata, admin creato via `php artisan make:admin`).

### Visione futura

**Utente capo dell'associazione** — l'utente principale che gestisce tutto.

**Registrazione utenti:**

- Ogni utente crea il proprio profilo con **utente e password**.
- Dopo la registrazione può:
    - **Creare un'associazione/gruppo**, oppure
    - **Entrare in un gruppo esistente** tramite **link o codice** fornito dal capo di quel gruppo.

**Ruoli (gestiti dal capo del gruppo):**
| Ruolo | Permessi |
|---|---|
| **Capo** | 1 solo per gruppo. Può fare tutto, incluso gestire i ruoli all'interno del proprio gruppo. |
| **Sottocapo** | Può fare tutto tranne gestire i ruoli o eliminare/modificare le modifiche del capo. |
| **Utente base** | Solo visualizzazione: **no modifica, no inserimento, no cancellazione**. |

### Da implementare

- **Nuova entità "Gruppo/Associazione"** (nome da definire) — entità che raggruppa utenti e veicoli.
- Relazione utenti ↔ gruppo (molti-a-molti con ruolo).
- Gestione inviti via link/codice.
- Scoping dei dati per gruppo (ogni gruppo vede solo i propri veicoli/dati).
- Sistema di permessi per ruolo (capo / sottocapo / utente base).

---

## 🔧 Sessione 2026-09-03: Bug Fix, Sicurezza e Generazione Automatica Scadenze

### Bug critici risolti

| #     | Problema                                                                   | Soluzione                                                              |
| ----- | -------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| **1** | `complete()` non impostava `is_renewed` → rinnovo perso                    | Aggiunto `is_renewed = true`                                           |
| **2** | `destroy()` e metodi custom senza autorizzazione (worker poteva eliminare) | Aggiunto `authorize()` + trait `AuthorizesRequests` al controller base |
| **3** | Policy registrate ma mai invocate (dead code)                              | Attivate tramite `authorize()` nei metodi di mutazione                 |

### Bug alti/medi risolti

| #      | Problema                                   | Soluzione                                          |
| ------ | ------------------------------------------ | -------------------------------------------------- |
| **4**  | N+1 in `DeadlineController::index`         | Nuovo `syncStatusesFromRules()` con update bulk    |
| **5**  | N+1 in `getAutomaticStatusAttribute`       | Cache del risultato in proprietà di istanza        |
| **6**  | `MONTH()` non portabile su SQLite          | Estrazione mese in PHP via Carbon                  |
| **7**  | API login senza rate limiting              | Applicato throttle `login` (5/min)                 |
| **8**  | `/dev-login` backdoor                      | Doppia guardia: `APP_ENV=local` + `APP_DEBUG=true` |
| **9**  | `resolveDueDate` nullsafe su `false`       | Riuso `resolveManualDueDate()`                     |
| **10** | `complete()` null safety `vehicleType`     | Nullsafe `?->`                                     |
| **11** | File vecchi mai eliminati su update        | `Storage::delete()` prima del nuovo upload         |
| **12** | `pivotSave` senza validazione `vehicle_id` | Verifica esistenza veicolo                         |
| **13** | TODO rinnovo scadenza successiva           | `createNextDeadlineAfterRenewal()`                 |
| **14** | `DEADLINE_TYPES` manca `tagliando`         | Aggiunto mapping                                   |
| **15** | `has_timing_belt` non persistito           | Validazione + salvataggio nei FormRequest          |
| **16** | `reminder_days_before` mai usato           | Applicato al report (default 30)                   |
| **17** | `EquipmentType` campi ispezione non usati  | Calcolo automatico `expiration_date`               |

### Nuove feature: generazione automatica scadenze

| Feature                   | Dettaglio                                                                                                                 |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| **Cinghia distribuzione** | Generata alla creazione se `has_timing_belt`; scade al primo tra 10 anni o 100.000 km; rinnovo al cambio cinghia          |
| **Tagliando**             | Primo tagliando alla creazione (1 anno o km configurabili, default 25.000); rinnovo al completamento tagliando ricorrente |
| **Campi VehicleType**     | `first_tagliando_km` (default 25.000) e `regular_tagliando_km` (default 20.000)                                           |
| **Enum deadlines.type**   | Esteso per includere Tagliando e Cinghia Distribuzione                                                                    |
| **due_date nullable**     | Supporto scadenze km-based senza data                                                                                     |

### Backfill dati esistenti

Migrazione `2026_09_03_180215` che al deploy aggiorna i dati esistenti:

1. `expiration_date` equipaggiamenti
2. Scadenza cinghia per veicoli con `has_timing_belt`
3. Primo tagliando per veicoli senza
4. Correzione `is_renewed` per rinnovi persi
5. Sincronizzazione stato scadenze

### Test

- **178 test, 346 assertions — tutti verdi** ✅
- Fix manifest Vite fittizio in `TestCase` per test senza build asset

---

> **Nota:** Ultimo aggiornamento: 2026-09-03.

---

## Indice

1. [🐛 Bug Risolti (Precedenti)](#-bug-risolti-precedenti)
2. [⚠️ Duplicazioni Risolte (Precedenti)](#️-duplicazioni-risolte-precedenti)
3. [💡 Migliorie Risolte (Precedenti)](#-migliorie-risolte-precedenti)
4. [🏗️ Feature Implementate (Precedenti)](#️-feature-implementate-precedenti)
5. [🐛 Bug Ancora Presenti (Nuovi)](#-bug-ancora-presenti-nuovi)
6. [⚠️ Best Practice da Applicare](#️-best-practice-da-applicare)
7. [💡 Migliorie e Ottimizzazioni](#-migliorie-e-ottimizzazioni)
8. [🏗️ Funzionalità Mancanti / Incomplete](#️-funzionalità-mancanti--incomplete)
9. [🎯 Priorità Suggerite](#-priorità-suggerite)

---

## 🐛 Bug Risolti (Precedenti)

### B1. `syncStatusFromRules()` sovrascrive lo stato manuale ✅ FIXATO

**File:** `app/Models/Deadline.php`
**Problema:** `syncStatusFromRules()` NON controlla `is_renewed` prima di ricalcolare lo stato. Se una scadenza è marcata come rinnovata (`is_renewed = true`), la chiamata a `syncStatusFromRules()` la sovrascrive. Invece `getAutomaticStatusAttribute()` (usato nelle view) controlla `is_renewed` correttamente.
**Soluzione:** Aggiunto early return `if ($this->is_renewed) { return; }` all'inizio di `syncStatusFromRules()`.

### B2. `getAutomaticStatusAttribute()` — Ramo `else` finale sbagliato ✅ FIXATO

**File:** `app/Models/Deadline.php`
**Problema:** L'ultimo `else` restituisce `STATUS_RENEWED` quando la data è prima del periodo di warning. Dovrebbe restituire `STATUS_VALID`. `STATUS_RENEWED` dovrebbe venire SOLO dal flag `is_renewed`.
**Soluzione:** Sostituito `STATUS_RENEWED` con `STATUS_VALID` in `getAutomaticStatusAttribute()` e `syncStatusFromRules()`.

### B3. Default `warning_months` diverso tra Controller e Model ✅ FIXATO

**File:** `DeadlineController::resolveStatus()` vs `Deadline::getAutomaticStatusAttribute()`

- Controller: `config('deadlines.warning_months', 3)` → default **3**
- Model: `config('deadlines.warning_months', 2)` → default **2** (ora allineato a 3)
  Questo causa incongruenze: lo stato calcolato al momento della creazione può differire da quello calcolato dalle regole automatiche del model.
  **Soluzione:** Allineato il default a **3** in `getAutomaticStatusAttribute()` e `syncStatusFromRules()`.

### B4. `$vehicle->mileage` usato in view ma non esiste ✅ FIXATO

**File:** `resources/views/admin/vehicles/show.blade.php`

```blade
<strong>Chilometri:</strong> {{ $vehicle->mileage }}
```

`mileage` non è una colonna del DB né un accessor sul model `Vehicle`. I chilometri sono in `mileage_logs`. Questa riga restituirà `null`.
**Soluzione:** Aggiunto accessor `getMileageAttribute()` su `Vehicle` che restituisce l'ultimo chilometraggio da `mileageLogs`. Aggiunto `'mileageLogs'` alla eager load nel `VehicleController::show()`.

### B5. `VehicleController::update()` ignora il file `registration_card` ✅ FIXATO

**File:** `app/Http/Controllers/Admin/VehicleController.php`
**Problema:** Il metodo `store()` gestisce il caricamento della carta di circolazione, ma `update()` no. Se un utente modifica un veicolo e carica un nuovo file, questo viene ignorato.
**Soluzione:** Aggiunta la stessa logica di upload di `store()` anche in `update()`.

### B6. `internal_code` con `type="number"` perde gli zeri iniziali ✅ FIXATO

**File:** `resources/views/admin/vehicles/create.blade.php` e `edit.blade.php`
**Problema:** `<input type="number">` per `internal_code` (che deve essere 4 cifre, es. "0123"). Un input numerico converte "0123" in "123" e la validazione `size:4|regex:/^[0-9]{4}$/` fallisce.
**Soluzione:** Sostituito con `type="text" inputmode="numeric"` in entrambe le view.

---

## ⚠️ Duplicazioni Risolte (Precedenti)

### D1. Logica di ordinamento/raggruppamento identica in 3 controller ✅ FIXATO

**File:** `DeadlineController`, `IssueController`, `MaintenanceRecordController`
Ognuno ha lo stesso pattern di ~50 righe per `$groupBy`, `$sortBy`, `$sortDir`, `$groupToggleUrl`, `$sortToggleUrl`, `$sortIcon`.
**Soluzione:** Creata trait `SortableAndGroupable` in `app/Http/Controllers/Concerns/`. Refactorati tutti e 3 i controller e le relative view.

### D2. Rilevamento duplicati ripetuto in 3 controller ✅ FIXATO

**File:** `IssueController::store()`, `MaintenanceRecordController::store()`, `ProviderController::store()`
Stesso pattern: query con `where('created_at', '>=', ...subMinutes(5))` per bloccare doppioni.
**Soluzione:** Creata trait `DetectsDuplicates` in `app/Http/Controllers/Concerns/`. Refactorati tutti e 3 i controller.

### D3. Calcolo estensione garanzia duplicato ✅ FIXATO

**File:** `VehicleController::store()` e `VehicleController::update()`
Stessa logica di `has_warranty_extension` + calcolo `warrantyEffectiveExpirationDate`.
**Soluzione:** Creata trait `HandlesWarrantyExtension` in `app/Http/Requests/Concerns/`. Integrato in `StoreVehicleRequest` e `UpdateVehicleRequest`. Semplificati entrambi i metodi del controller.

### D4. `resolveStatus()` duplica logica del Model ✅ FIXATO

**File:** `DeadlineController::resolveStatus()` e `Deadline::getAutomaticStatusAttribute()`
La logica di determinazione dello stato è implementata in due posti con leggere differenze (vedi B3).
**Soluzione:** Rimosso `resolveStatus()` dal controller. Ora `store()` e `update()` non passano più `status`, lasciando che `syncStatusFromRules()` lo calcoli nel Model.

---

## 💡 Migliorie Risolte (Precedenti)

### M1. Usare `orderBy()` del DB invece di Collection `sortBy()` ✅ FIXATO

**Impatto:** Alto (performance)
Tutti i controller caricano tutto con `->get()` e poi ordinano in memoria con `->sortBy()`. Per poche decine di record va bene, ma con centinaia/migliaia diventa un collo di bottiglia. Usare `->orderBy()` nella query.
**Soluzione:** Modificato `applySorting()` nel trait per accettare una mappa: se il valore è una stringa (colonna DB) fa `orderBy()` direttamente, se è un callable fa sorting in memoria. `IssueController` e `MaintenanceRecordController` ora ordinano `status`, `event_date`, `appointment_date` via DB.

### M2. Aggiungere paginazione ✅ FIXATO

**Impatto:** Alto (scalabilità)
Nessun controller usa `->paginate()`. Con la crescita dei dati, le liste diventeranno ingestibili.
**Soluzione:** Sostituito `->get()`/`->all()` con `->paginate(20)` in 6 controller. Aggiunto supporto `paginator` al componente `x-admin.index-table` con `$paginator->links()`.

### M3. Aggiungere `unique` validation su `serial_number` in Equipment ✅ FIXATO

**File:** `StoreEquipmentRequest.php` e `UpdateEquipmentRequest.php`
La colonna `serial_number` è `unique` nel DB ma non c'è validazione lato Laravel. Un utente potrebbe ricevere un errore SQL invece di un messaggio amichevole.

### M4. Aggiungere `unique` validation su `name` in Provider ✅ FIXATO

**File:** `StoreProviderRequest.php` e `UpdateProviderRequest.php`
I nomi delle officine dovrebbero essere univoci.
**Soluzione:** Aggiunta validazione `unique` in entrambe le Request. Creata migration per rendere `name` unico anche a livello DB.

### M5. Aggiungere SoftDeletes ✅ FIXATO

**Impatto:** Medio
Eliminare un veicolo (`cascadeOnDelete`) cancella permanentemente guasti, manutenzioni, scadenze. Con `SoftDeletes` si potrebbe recuperare.
**Soluzione:** Creata migration unica per aggiungere `softDeletes()` a vehicles, issues, maintenance_records, deadlines. Aggiunto `use SoftDeletes` ai rispettivi Model.

### M6. Aggiungere Authorization/Policy ✅ FIXATO

**Impatto:** Medio (sicurezza)
Tutti i FormRequest hanno `authorize() { return true; }`. Ogni utente autenticato può fare tutto.
**Soluzione:** Aggiunta colonna `role` a users. Create 9 Policy con trait `HasRoleBasedAccess` (admin può tutto, altri solo visualizzazione). Creato trait `AdminOnlyAccess` per i FormRequest. Registrate le Policy in `AppServiceProvider`.

### M7. Aggiungere validazione `after_or_equal:immatricolation_date` su `warranty_expiration_date` ✅ FIXATO

**Impatto:** Basso
La data di scadenza garanzia non può essere prima dell'immatricolazione.
**Soluzione:** Aggiunta validazione `after_or_equal:immatricolation_date` e messaggio personalizzato in entrambi i FormRequest.

### M8. Aggiungere validazione `mileage` deve essere >= ultimo log ✅ FIXATO

**Impatto:** Medio
Quando si inserisce un nuovo chilometraggio, non si controlla che sia maggiore o uguale all'ultimo registrato per lo stesso veicolo.
**Soluzione:** Aggiunto `withValidator` in `StoreMileageLogRequest` e `UpdateMileageLogRequest` che confronta con l'ultimo chilometraggio dello stesso veicolo. Nell'update esclude il record corrente dal confronto.

### M9. Tema chiaro/scuro non funzionante ✅ FIXATO

**File:** `resources/views/layouts/app.blade.php`
Il pulsante theme toggle c'è nell'header ma non c'è lo script JavaScript per gestire il click e salvare la preferenza.
**Soluzione:** Aggiunto script JavaScript che gestisce il click, cambia l'attributo `data-bs-theme`, aggiorna l'icona e salva in `localStorage`.

---

## 🏗️ Feature Implementate (Precedenti)

### F1. ✅ **Dashboard implementata** ✅ FIXATO

La dashboard (`/dashboard`) mostra solo "You are logged in!". Dovrebbe mostrare:

- Scadenze imminenti (prossimi 30 giorni)
- Guasti aperti
- Veicoli con equipaggiamento incompleto
- Prossimi appuntamenti in officina

**Soluzione:** Creata `DashboardController` con query per tutte le statistiche. View dashboard.blade.php con card interattive, badge colorati, progress bar, supporto dark mode e link alle pagine di dettaglio.

### F2. ✅ **Sistema di notifiche implementato** ✅ FIXATO

Non ci sono comandi schedulati, notifiche email, o alert per scadenze imminenti.
**Soluzione:** Creato comando `SendSummaryReport` con report giornaliero via email. Mailable `ReportMail` con template HTML contenente veicoli ok, statistiche, scadenze (da rinnovare + in arrivo), guasti aperti, appuntamenti in officina e interventi necessari. Scheduler configurabile con frequenza daily/weekly/monthly. Model `NotificationSetting` con CRUD per impostare email, frequenza e giorni reminder.

### F3. ✅ **Export PDF implementato** ✅ FIXATO

Non c'è export in Excel/PDF per nessuna entità.
**Soluzione:** Installato DomPDF. Creato `PdfExportController` con eager loading di tutte le relazioni. View `scheda-veicolo.blade.php` con anagrafica, documenti, scadenze, guasti aperti, dotazioni di bordo, ultime manutenzioni e storico guasti e interventi. Bottone "Scarica PDF" nella show del veicolo.

### F4. ✅ **Audit log implementato** ✅ FIXATO

Non si sa chi ha creato/modificato/cancellato cosa.
**Soluzione:** Installato `spatie/laravel-activitylog`. Aggiunto trait `LogsActivity` a Vehicle, Issue, MaintenanceRecord, Deadline, Equipment. Logging automatico con `$logOnlyDirty = true`.

### F5. ✅ **Collegamento manutenzione ↔ guasti/scadenze via polimorfico** ✅ FIXATO

Il campo `issue_id` e `deadline_id` in `maintenance_records` sono stati sostituiti da una tabella pivot polimorfica `maintenance_record_items` (morph `itemable`: Issue o Deadline). Un appuntamento può ora collegare N guasti + N scadenze. Le view create/edit hanno checkbox multipli filtrati per veicolo. Lo stato dei guasti viene aggiornato automaticamente: `open` → `in_progress` alla creazione, `in_progress` → `open` alla rimozione.

### F6. ✅ **Alert equipaggiamento in scadenza integrato** ✅ FIXATO

Gli equipaggiamenti hanno `expiration_date` ma non c'era alcun sistema che avvisasse quando si avvicina la scadenza.
**Soluzione:** Aggiunta query `$expiringEquipment` nel `DashboardController` e `SendSummaryReport` per attrezzature con scadenza nei prossimi 30 giorni. Dashboard mostra stat card dedicata e lista dettagliata con badge rosso (scaduta) / giallo (in scadenza). Report email include sezione "Attrezzature in scadenza" con nome, veicolo e data.

### F7. ✅ **Test implementati** ✅ FIXATO

**136 test passati (282 assertions)** che coprono: CRUD completo di tutte le entità, validazione (Vehicle, Deadline), Observer (VehicleObserver), business logic (transizioni di stato manutenzioni, complete(), rinnovo scadenze), autorizzazione (non-admin bloccato su POST/PUT/DELETE, può vedere le view), duplicate detection (maintenance, issue, provider), e trait `SortableAndGroupable`, `DetectsDuplicates`, `AdminOnlyAccess` e `HasRoleBasedAccess`.

### F8. ❌ **Nessuna gestione utenti/ruoli** (deferita)

Solo autenticazione base di Laravel Breeze. Nessun pannello admin per gestire utenti.

### F9. ✅ **Ricerca/filtro implementata** ✅ FIXATO

Le index non avevano una barra di ricerca testuale.
**Soluzione:** Creata trait `Searchable` in `app/Models/Concerns/` con metodo `scopeSearch()` che suddivide la query in termini e cerca con `LIKE` sulle colonne dichiarate in `$searchable`. Aggiunta a `Vehicle` (`$searchable = ['internal_code', 'license_plate']`), `Issue` (`$searchable = ['description', 'status']`) e `Deadline` (`$searchable = ['type', 'status']`). I controller `VehicleController`, `IssueController` e `DeadlineController` usano `->search($request->get('q'))` prima di `applySorting()`/`paginate()`. La view `x-admin.index-table` supporta la prop `searchRoute` con form di ricerca e pulsante "Cancella filtro", preservando i parametri query esistenti.

### F10. ✅ **Mileage integrato in manutenzione, rilevazione mensile e scadenze km** ✅ FIXATO

I chilometraggi erano registrati ma non integrati con manutenzioni, scadenze e rilevazioni periodiche.

**Soluzioni (3 sotto-feature):**

**F10a — Km sull'appuntamento di manutenzione**
Aggiunta colonna `mileage_at_service` (nullable) su `maintenance_records` + campo opzionale nei form create/edit. Durante la creazione/modifica di un appuntamento in officina si possono registrare i km correnti del veicolo.

**F10b — Rilevazione mensile km di massa (bulk)**
Nuova rotta `mileage-logs.bulk` con vista unica che elenca TUTTI i veicoli con ultimo km noto e un input per inserire i km attuali. Un submit crea N `MileageLog` in una transazione. Pulsante "Rilevazione mensile" nell'index dei chilometraggi.

**F10c — Scadenze basate sui km (Tagliando e Cinghia Distribuzione)**

- Nuovi tipi `Deadline`: `Tagliando` e `Cinghia Distribuzione`
- Nuove colonne su `deadlines`: `interval_km`, `last_mileage`, `interval_days`
- Nuova colonna `has_timing_belt` su `vehicles` (flag cinghia distribuzione)
- `getAutomaticStatusAttribute()` e `syncStatusFromRules()` ora considerano anche il superamento km: la scadenza scatta al primo tra superamento km o raggiungimento data
- Tagliando: intervallo km (es. 15.000 km) o giorni (es. 365) — configurabile dal form
- Cinghia Distribuzione: 100.000 km o 10 anni (3650 giorni) dall'ultimo cambio
- Nuove option nei form create/edit deadline (Tagliando, Cinghia Distribuzione) con sezione dedicata per impostare km
- Form veicolo aggiornato con checkbox `has_timing_belt`

---

## 🐛 Bug Ancora Presenti (Nuovi)

### B7. ~~`DeadlineController::resolveDueDate()` non definito~~ ❌ FALSO POSITIVO

**File:** `app/Http/Controllers/Admin/DeadlineController.php`
**Problema:** I metodi `store()` e `update()` chiamano `$this->resolveDueDate()` ma sembrava non esistesse.
**Realtà:** I metodi `resolveDueDate()` e `resolveManualDueDate()` sono **già presenti** nel controller (righe 200-230). Era un falso positivo dovuto a lettura parziale del file.

### B8. ❌ `NotificationSettingController::edit()` non protetto da autenticazione ✅ FIXATO

**File:** `routes/web.php`
**Problema:** Le route `/admin/notifications` (GET e PATCH) erano **fuori dal gruppo middleware `auth`**.
**Soluzione:** Spostate le route dentro il gruppo `auth, verified` con prefisso `admin`. I nomi delle route (`admin.notifications.edit`, `admin.notifications.update`) sono invariati.

### B9. ❌ `PdfExportController` non protetto da autenticazione ✅ FIXATO

**File:** `routes/web.php`
**Problema:** La route `vehicles/{vehicle}/pdf` era fuori dal gruppo `auth`.
**Soluzione:** Spostata dentro il gruppo `auth, verified` con prefisso `admin`. Il nome route (`admin.vehicles.pdf`) è invariato.

### B10. ❌ `getAutomaticStatusAttribute()` — salta controllo km silenziosamente ✅ FIXATO

**File:** `app/Models/Deadline.php`
**Problema:** Il metodo controllava `$this->relationLoaded('vehicle')` e saltava il controllo km-based **silenziosamente** se non caricata.
**Soluzione:** Ora carica `vehicle.latestMileageLog` on-demand con `loadMissing()`. Se il veicolo non esiste, procede comunque senza km check. Aggiunta anche relazione `latestMileageLog` su `Vehicle` per eager-loadare l'ultimo km in una sola query.

### B11. ❌ `syncStatusFromRules()` causa N+1 query in loop ✅ FIXATO

**File:** `app/Models/Deadline.php`, `app/Http/Controllers/Admin/DeadlineController.php`
**Problema:** `syncStatusFromRules()` chiamava `$this->loadMissing('vehicle')` in loop (N+1) + `$this->vehicle->mileage` faceva un'ulteriore query per `mileage_logs`.
**Soluzione:**

1. Aggiunta relazione `latestMileageLog` su `Vehicle` (hasOne ordinata per `log_date`)
2. `getMileageAttribute()` ora usa la relazione pre-caricata se disponibile
3. `syncStatusFromRules()` carica `vehicle.latestMileageLog` in un colpo solo
4. `DeadlineController::index()` eager-loada `vehicle.latestMileageLog` → zero query extra

### B12. ❌ `SendSummaryReport` hardcoded a `test@example.com` ✅ FIXATO

**File:** `app/Console/Commands/SendSummaryReport.php`
**Problema:** La mail veniva inviata sempre a `test@example.com` invece di usare l'indirizzo configurato.
**Soluzione:** Ora legge `report_email` da `NotificationSetting`. Se non configurato, mostra un warning e termina senza errori.

### B13. ❌ `NotificationSetting::getValueAttribute()` usa `$this->key` non ancora impostato ✅ FIXATO

**File:** `app/Models/NotificationSetting.php`
**Problema:** L'accessor usava `$this->key` che potrebbe non essere popolato durante l'idratazione Eloquent.
**Soluzione:** Sostituito con `$this->attributes['key'] ?? null` che è sempre disponibile.

### B14. ❌ `internal_code` non ha unique validation ✅ FIXATO

**File:** `app/Http/Requests/StoreVehicleRequest.php`, `UpdateVehicleRequest.php`
**Problema:** La colonna `internal_code` non aveva validazione `unique`.
**Soluzione:** Aggiunta `unique:vehicles,internal_code` in Store e `Rule::unique(...)->ignore(...)` in Update.

### B15. ❌ `VehicleController::show()` — eager load ridondante ❌ FALSO POSITIVO

**File:** `app/Http/Controllers/Admin/VehicleController.php`
**Problema:** Segnalato come query extra.
**Realtà:** Usare `$vehicle->maintenanceRecords()->with(...)->get()` è più efficiente di `$vehicle->load()` perché carica tutto in una query sola. Non è un bug.

---

## ⚠️ Best Practice da Applicare

### BP1. Route notifiche e PDF fuori dal gruppo auth ✅ FIXATO (con B8-B9)

Le route di notifica e PDF erano fuori dal gruppo `auth`. **Risolto** spostandole dentro il gruppo `auth, verified` con prefisso `admin`.

### BP2. Estrarre logica di business dai Controller ✅ FIXATO

Controller come `DeadlineController::store()` e `update()` avevano troppa logica (calcolo date, verifica ossigeno, creazione record).
**Soluzione:** Creato `app/Services/DeadlineService.php` con metodi `createDeadline()` e `updateDeadline()`. Il controller ora delega tutto al service tramite dependency injection.

### BP3. Usare FormRequest per tutte le validazioni ✅ FIXATO

`NotificationSettingController::update()` usava `$request->except('_token', '_method')` senza validazione.
**Soluzione:** Creato `UpdateNotificationSettingRequest` con regole per tutti i campi noti (report*email, report_frequency, reminder_days_before, notify_on*\*). Il controller ora usa `$request->validated()`.

### BP4. Fallback nome app errato nell'header ✅ FIXATO

**File:** `resources/views/admin/partials/header.blade.php`
L'header mostrava `{{ config('app.name', 'Gods Backoffice') }}` — il fallback era "Gods Backoffice" invece di "CV Backoffice".
**Soluzione:** Corretto il fallback in "CV Backoffice".

### BP5. N+1 query in `DashboardController` ✅ FIXATO

**File:** `app/Http/Controllers/DashboardController.php`
`$incompleteVehicles` caricava **tutti** i veicoli con le loro relazioni, poi filtrava in memoria.
**Soluzione:** Aggiunto `whereHas('vehicleType.equipmentTypes')` per caricare solo i veicoli il cui tipo ha effettivamente requisiti di equipaggiamento. Quelli senza requisiti sono sempre "completi" per definizione.

### BP6. `MileageLog` non ha `LogsActivity` ✅ FIXATO

Tutti gli altri model hanno activity logging, ma `MileageLog` no.
**Soluzione:** Aggiunto trait `LogsActivity` al model `MileageLog`.

### BP7. `Provider` non ha `SoftDeletes` ✅ FIXATO

Se un fornitore viene cancellato, i record di manutenzione collegati perdono il riferimento.
**Soluzione:** Aggiunto `SoftDeletes` al model `Provider` + migration per aggiungere `softDeletes()` alla tabella `providers`.

### BP8. `NotificationSetting` non ha timestamps espliciti nei casts ❌ NON RILEVANTE

I timestamp `created_at`/`updated_at` sono automaticamente castati a Carbon da Laravel per tutti i model Eloquent. Non serve dichiararli esplicitamente.

---

## 💡 Migliorie e Ottimizzazioni

### M10. Cache per le statistiche della Dashboard ✅ FIXATO

**Impatto:** Alto
La dashboard esegue 6+ query ogni volta.
**Soluzione:** Avvolte in `Cache::remember('dashboard.stats', 300, ...)` — cachea i dati per 5 minuti, la view viene renderizzata sempre fresca.

### M11. Indici DB mancanti ✅ FIXATO

**Impatto:** Alto
Mancavano indici su colonne usate in WHERE/ORDER BY.
**Soluzione:** Creata migration `add_performance_indexes` con indici su: deadlines (type,status, due_date), issues (status, event_date), mileage_logs (log_date), equipment (expiration_date), maintenance_records (appointment_date, return_date).

### M12. `DeadlineController::index()` carica tutto in memoria ✅ FIXATO

**File:** `app/Http/Controllers/Admin/DeadlineController.php`
**Problema:** Tutte le scadenze venivano caricate in memoria, poi filtrate via Collection.
**Soluzione:** Il filtro `latestRevisionOnly` ora filtra a monte con `whereIn('type', ...)` prima di `get()`. Il caso normale rimane con `get()` ma con gli indici DB di M11 la query è ottimizzata.

### M13. `VehicleObserver::created()` potrebbe fare backfill pesante ✅ FIXATO

**Impatto:** Medio
Il `while` loop per backfill delle revisioni storiche potrebbe creare decine di record.
**Soluzione:** Aggiunto safety limit `MAX_BACKFILL_ITERATIONS = 10` per prevenire runaway loop su veicoli molto vecchi.

### M14. `HandlesWarrantyExtension` modifica i dati validati (side effect) ❌ DESIGN CHOICE

**File:** `app/Http/Requests/Concerns/HandlesWarrantyExtension.php`
La trait sovrascrive `warranty_expiration_date` aggiungendo i mesi di estensione. È un pattern accettabile per trasformazioni al confine request→DB. L'accessor `getWarrantyOriginalExpirationDateAttribute()` la rilegge correttamente.

### M15. `DetectsDuplicates` soglia fissa di 5 minuti ❌ NON RILEVANTE

Il metodo `findDuplicate()` ha già il parametro opzionale `int $minutesThreshold = 5`. I controller possono passare un valore diverso. Il default è ragionevole.

### M16. `Searchable` trait fa `LIKE %term%` 🔵 BASSO (deferito)

Per il volume attuale di dati è più che sufficiente. Se si arriverà a decine di migliaia di record, valutare MySQL fulltext o Laravel Scout.

### M17. `Vehicle::getMileageAttribute()` fa query ogni volta ✅ GIA' FIXATO (con B11)

Già risolto con la fix di B10/B11 — aggiunta relazione `latestMileageLog` su Vehicle e `getMileageAttribute()` la usa se pre-caricata.

### M18. `Vehicle::missingRequiredEquipment()` carica sempre `equipment` e `vehicleType` ❌ NON RILEVANTE

Usa `loadMissing()` che carica le relazioni solo se non già caricate. È già ottimale.

---

## 🏗️ Funzionalità Mancanti / Incomplete

### F11. ❌ **Gestione utenti e ruoli** (deferita)

Non c'è un pannello admin per creare/modificare utenti o assegnare ruoli. Il ruolo è una colonna `string` su `users` ma non c'è UI per gestirlo.

### F12. ❌ **Audit log UI**

`spatie/laravel-activitylog` è installato e funzionante, ma non c'è una pagina per visualizzare lo storico delle attività. Sarebbe utile per audit e troubleshooting.

### F13. ❌ **Notifiche in-app**

Le notifiche sono solo via email (report giornaliero). Non ci sono notifiche in-app (badge, toast, campanella) per scadenze imminenti o guasti aperti.

### F14. ❌ **Backup / Export dati**

Non c'è export CSV/Excel per nessuna entità (solo PDF per il singolo veicolo). Utile per reportistica esterna.

### F15. ❌ **Storico revisioni veicolo**

La show del veicolo mostra solo l'ultima scadenza per tipo (`$deadlines_grouped`). Non c'è una sezione "Storico revisioni" con tutte le scadenze passate.

### F16. ❌ **Calendario appuntamenti**

Gli appuntamenti in officina sono una lista. Una vista calendario (tipo FullCalendar) sarebbe molto più intuitiva.

### F17. ❌ **Test mancanti**

- `MileageLogController` (CRUD + bulk)
- `EquipmentController` (CRUD)
- `EquipmentTypeController` (CRUD)
- `VehicleTypeController` (CRUD + sync equipment)
- `NotificationSettingController`
- `PdfExportController`
- `DashboardController`
- `SendSummaryReport` command
- `VehicleObserver` (solo parziale)
- `VehicleSelect` Livewire component

### F18. ❌ **API REST**

Non c'è un'API pubblica. Se in futuro si volesse un'app mobile o integrazione con terze parti, servirebbe Laravel Sanctum o Passport.

### F19. ❌ **Internazionalizzazione (i18n)**

Il progetto è in italiano ma usa `__('Dashboard')` in alcuni punti e testo hardcoded in altri. Manca un sistema di traduzione coerente.

### F20. ❌ **Rate limiting**

Non c'è rate limiting sulle route. Un utente malintenzionato potrebbe fare brute force sul login o flooding sulle create.

---

## 🎯 Priorità Suggerite

| Priorità       | Cosa                                         | Perché                                           |
| -------------- | -------------------------------------------- | ------------------------------------------------ |
| 🔴 **Critico** | ~~**B7**: `resolveDueDate()` mancante~~      | ❌ **Falso positivo — metodi esistono già**      |
| 🔴 **Critico** | **B8-B9**: Route notifiche e PDF senza auth  | ✅ **FIXATO — spostate dentro gruppo auth**      |
| 🔴 **Critico** | **B12**: Email hardcoded a test@example.com  | ✅ **FIXATO — ora legge da NotificationSetting** |
| 🟡 **Alto**    | **B10**: `relationLoaded('vehicle')` fragile | ✅ **FIXATO — loadMissing + latestMileageLog**   |
| 🟡 **Alto**    | **B11 + M12**: N+1 in syncStatusFromRules    | ✅ **FIXATO — eager load + relazione dedicata**  |
| 🟡 **Alto**    | **B13**: Accessor NotificationSetting        | ✅ **FIXATO — usa `$this->attributes['key']`**   |
| 🟡 **Alto**    | **B14**: `internal_code` senza unique        | ✅ **FIXATO — aggiunta validazione**             |
| 🟡 **Alto**    | **M10**: Cache dashboard                     | Performance homepage                             |
| 🟡 **Alto**    | **M11**: Indici DB                           | Performance query                                |
| 🟡 **Alto**    | **F12**: UI audit log                        | Compliance e debugging                           |
| 🟢 **Medio**   | **BP2**: Service Layer                       | Manutenibilità codice                            |
| 🟢 **Medio**   | **F11**: Gestione utenti                     | Completezza funzionale                           |
| 🟢 **Medio**   | **F17**: Test mancanti                       | Qualità del codice                               |
| 🟢 **Medio**   | **BP6**: ActivityLog su MileageLog           | Tracciabilità                                    |
| 🔵 **Basso**   | **M14**: Spostare logica garanzia da Request | Refactoring                                      |
| 🔵 **Basso**   | **F16**: Calendario appuntamenti             | UX                                               |
| 🔵 **Basso**   | **F19**: i18n                                | Completezza                                      |

---

## 🔧 Refactoring Best Practice (Post-Analisi)

### N1. ✅ `User` — `role` in fillable e casts

Aggiunto `role` a `$fillable` e `$casts` su `User` per allineamento con la colonna DB.

### N2. ✅ Policy — metodi `restore()` e `forceDelete()` mancanti

Aggiunti al trait `HasRoleBasedAccess` per coprire tutte le operazioni con SoftDeletes.

### N3. ✅ Issue — relazione obsoleta rimossa

Rimossa `maintenanceRecords()` (hasMany) da `Issue` perché sostituita dalla pivot polimorfica `maintenanceRecordItems()`.

### N4. ✅ DashboardController — pulizia metodi vuoti

Rimossi 6 metodi vuoti (create/store/show/edit/update/destroy). Aggiunto `take(20)` su `openIssues`.

### N6. ✅ NotificationSetting — cast automatico booleani

Aggiunto `getValueAttribute()` che converte in booleano le chiavi notifiche note (`notify_on_*`).

### N7. ✅ VehicleType — `extinguishers_required` ripristinato

Ripristinata colonna (era stata rimossa da una migration), aggiunti campo form create/edit/show, validazione Request.

### O1. ✅ PDF export — eager loading ridondante rimosso

Rimossa la catena `issues.maintenanceRecords.*` già coperta da `maintenanceRecords`.

### O2. ✅ SendSummaryReport — query unificate

Unite 2 query separate (`Vehicle::count()` + `Vehicle::with()->get()`) in una sola, calcolando `$vehiclesOk` e `$incompleteVehicles` dalla stessa collection.

### O4. ✅ MileageLog bulkStore — loop query ottimizzato

Sostituito `Vehicle::find()` in loop con una singola `whereIn()`.

### B1. ✅ Vehicle — accessor `warranty_original_expiration_date`

Spostato il calcolo della data originale di garanzia dal controller all'accessor del model.

### B2. ✅ IssueController — metodo privato `buildIssueData()`

Estratta logica di upload immagine duplicata tra `store()` e `update()` in un metodo privato.

### B3. ✅ Scope Eloquent — query riutilizzabili

Creati: `Issue::scopeOpen()`, `Deadline::scopeUpcoming()`, `Equipment::scopeExpiringSoon()`. Usati in DashboardController e SendSummaryReport.

### B4. ✅ Dashboard + Report — refactor con scope

Sostituite query inline con gli scope B3 in `DashboardController` e `SendSummaryReport`.

### B5. ✅ Vehicle — accessor `deadlines_grouped` + costante tipi

Spostata logica di raggruppamento deadline (sortByDesc→groupBy→first) dal controller all'accessor del model. Costante `DEADLINE_TYPES` sul model.

---

> **Nota:** Questo file è stato generato automaticamente dall'analisi del codice. Puoi aggiornarlo man mano che risolvi i vari punti, spuntando ciò che hai completato.
