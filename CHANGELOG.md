# Changelog

Tutte le modifiche significative a questo progetto saranno documentate in questo file.

## [Unreleased]

## [v1.2.7] - 2026-09-14

### Fixed

- Lo storico chilometraggi trattava il valore 0 come una lettura km reale: `VehicleObserver` crea le scadenze iniziali di tagliando/cinghia con `last_mileage=0` come segnaposto "non ancora noto", producendo solo falsi conflitti con la cronologia reale (sia al salvataggio che nel comando di backfill). 0 ora viene ignorato come null.

### Added

- `mileage-logs:backfill` supporta `--interactive` (insieme a `--apply`): per ogni conflitto reale chiede come procedere — saltarlo, registrarlo comunque ignorando la cronologia, o inserire un valore km diverso — invece di poterlo solo saltare automaticamente.

## [v1.2.6] - 2026-09-14

### Added

- Nuovo comando `php artisan mileage-logs:backfill [--vehicle=ID] [--apply]`: registra nello storico chilometraggi le letture km già presenti su scadenze e appuntamenti ma mai riportate lì, per dati inseriti prima che questo collegamento esistesse.

### Fixed

- Il km inserito su una scadenza (Revisione/Tagliando/Cinghia) o su un appuntamento completato non veniva mai registrato nello storico chilometraggi del veicolo: restava isolato in quel record, non compariva come "ultimo km" nell'indice veicoli e — soprattutto — non alimentava il calcolo automatico dello stato scaduto/in scadenza per tagliando/cinghia, che confronta il km con l'ultimo letto nello storico. Ora ogni lettura viene registrata automaticamente lì, rispettando la stessa coerenza cronologica già applicata all'inserimento manuale.
- Il completamento di un appuntamento in officina aveva una propria logica di rinnovo scadenze completamente separata da quella del form di modifica, mai coperta dalle guardie anti-duplicati introdotte in precedenza: non collegava mai la scadenza successiva a quella rinnovata (renews_deadline_id), e non riportava il km rilevato all'appuntamento su ministeriale/ossigeno (solo su tagliando/cinghia). Unificata con la stessa logica del form di modifica.

## [v1.2.5] - 2026-09-14

### Fixed

- Elenco scadenze: il dropdown "tipologia" era annidato dentro il box di ricerca e ne usciva schiacciato/con doppio bordo; ora è affiancato ad esso.
- Elenco scadenze: le intestazioni dei sotto-gruppi annidati (es. tipologia dentro veicolo) avevano lo stesso stile in grassetto di quelle di primo livello, facili da confondere con un nuovo gruppo. Ora hanno uno stile più leggero e distinto, e le intestazioni di primo livello hanno un bordo di stacco dalla riga precedente.

## [v1.2.4] - 2026-09-14

### Added

- Nuovo comando `php artisan deadlines:backfill-renewal-links [--vehicle=ID] [--apply]`: collega retroattivamente le scadenze periodiche esistenti (Revisione Ministeriale/Impianto Ossigeno/Tagliando) alla scadenza che rinnovano (`renews_deadline_id`), per dati creati prima che questo collegamento esistesse. Anteprima di default, `--apply` per applicare davvero, `--vehicle` per limitare a un veicolo.
- Elenco scadenze: filtro per tipologia (dropdown, accanto ai chip di stato già esistenti).
- Elenco scadenze: i raggruppamenti "Tipologia" e "Veicolo" ("Grp" in colonna) ora sono combinabili invece di escludersi a vicenda — es. veicolo poi tipologia, annidati.

### Fixed

- La ricerca in Scadenze e Guasti non trovava nulla cercando un veicolo per codice/targa, nonostante il placeholder lo promettesse esplicitamente: cercava solo nei campi propri (tipo/stato o descrizione/stato), mai sul veicolo collegato.
- Scheda veicolo, card "Guasti": i guasti aperti/in lavorazione ora sono sempre in cima alla lista, indipendentemente dalla data (prima erano ordinati solo per data, e uno aperto più vecchio poteva finire sotto uno chiuso più recente).

## [v1.2.3] - 2026-09-14

### Fixed

- Il badge guasti aperti nella pagina veicolo mostrava il conteggio due volte (es. "⚠ 3 3 guasto/i aperto/i").

## [v1.2.2] - 2026-09-14

### Fixed

- Il fix di v1.2.1 non copriva tutti i casi: modificare la più vecchia di due Revisioni Ministeriali già rinnovate in catena (es. per aggiungere solo il km) poteva ricreare la scadenza che la rinnova già, anche se esisteva. Aggiunta una seconda guardia indipendente: se una scadenza dello stesso tipo rinnova già quella in modifica (`renews_deadline_id`), non ne viene creata un'altra, a prescindere dalla data ricalcolata.

## [v1.2.1] - 2026-09-14

### Fixed

- Riaprire in modifica una scadenza periodica (Revisione Ministeriale/Impianto Ossigeno/Tagliando) già rinnovata e salvare di nuovo (es. per annotare solo il km) creava un duplicato della scadenza successiva già generata al momento del rinnovo, invece di lasciarla invariata.
- Marcare una scadenza come rinnovata dal form di modifica non allineava più la colonna `status` a `renewed` (restava sullo stato precedente), disallineata dal flag `is_renewed` e invisibile al calcolo automatico della prossima data.

## [v1.2.0] - 2026-09-14

### Security

- **Isolamento tra gruppi applicato in modo sistematico su tutta l'applicazione.** Fino a questa versione lo scoping per gruppo esisteva solo nelle liste principali del backoffice; l'accesso a un singolo record, l'API mobile, gli export, la cache e i comandi schedulati non lo applicavano affatto, permettendo — a seconda del punto — a un utente autenticato di vedere, modificare o ricevere via email dati di un gruppo diverso dal proprio conoscendo/indovinando un id.
  - **API mobile** (`Api\VehicleController/IssueController/DeadlineController/MaintenanceRecordController`): `show()`/`index()` non filtravano per gruppo.
  - **Pannello admin**: le Policies (view/update/delete) verificavano solo il ruolo, mai l'appartenenza al gruppo del record — introdotto `HasGroupScopedAccess`, applicato a 6 Policies; scoping aggiunto anche a 5 controller `index()`, export CSV, import CSV, azioni bulk sui chilometraggi.
  - **Dashboard**: quasi nessun widget era filtrato per gruppo e il risultato era cachato sotto un'unica chiave globale, condivisa da tutti i gruppi per 5 minuti.
  - **Export PDF veicolo**: nessun controllo di gruppo sulla rotta.
  - **Notifiche schedulate ed email di riepilogo** (`app:generate-notifications`, `app:send-summary-report`): girano come comandi da console, senza utente autenticato, quindi lo scoping basato su `Auth::user()` non si applicava affatto — un capo/sottocapo riceveva notifiche ed email (con allegato PDF) su scadenze, guasti, attrezzature e appuntamenti di ogni gruppo, non solo del proprio.
- Rientrare in un proprio gruppo con il codice invito poteva retrocedere silenziosamente un capo/sottocapo a membro semplice (comportamento di `syncWithoutDetaching` sul pivot ruolo).
- Il backup del database via comando/pulsante non verificava alcuna autorizzazione.

### Fixed

- Corrette diverse N+1 query nella generazione notifiche e nell'invio report.
- Colore icona veicolo incoerente, tipi nascosti nell'elenco scadenze, 404 nell'eliminazione da pagina di dettaglio.

### Added

- Scadenze "Revisione": data di rinnovo modificabile manualmente, con catena di auto-rinnovo.

### Removed

- `RegisteredUserController`, codice morto irraggiungibile (nessuna rotta di registrazione pubblica: gli account si creano solo su invito/dal backoffice).

### Docs

- README aggiornato (versione Laravel, conteggio test, endpoint API, nota sull'assenza di registrazione pubblica).

## [v1.1.0] - 2026-09-12

### Added

- **Restyle completo "Dashboard Pro"**: tutta l'interfaccia (dashboard, veicoli, scadenze, guasti, appuntamenti, fornitori, km, attrezzature, tipi veicolo/attrezzatura, gruppi, utenti, impostazioni, notifiche, registro attività, login/auth, profilo) è stata migrata da Bootstrap al nuovo design system Dashboard Pro: sidebar + topbar, tema chiaro/scuro con switch a 3 posizioni, sidebar mobile a scomparsa, breadcrumb, tabelle e form uniformati.
- Notifiche: impostazioni (`report_email`, frequenza, giorni di preavviso, toggle `notify_on_*`) rese personali per account invece che globali; i toggle sono ora letti realmente da `GenerateNotifications`/`SendSummaryReport`, riscritti per operare per-utente.
- Pagina "Utenti" rimossa (duplicava i membri del gruppo): la creazione di un account ora avviene direttamente dalla pagina del gruppo ("Crea nuovo utente").
- Profilo utente accessibile anche cliccando su nome/avatar in fondo alla sidebar.
- Login e pagine di autenticazione: nuovo layout dedicato senza sidebar admin, occhiello mostra/nascondi password su ogni campo password dell'app.
- Scadenze: possibilità di annotare il chilometraggio anche per le revisioni (ministeriale/ossigeno), campo facoltativo e indipendente dal calcolo automatico della data.
- Appuntamenti (manutenzioni): nuovo campo "Note" facoltativo.
- Registro attività: filtro/visualizzazione basati su `subject_type` invece del sempre uguale `log_name`; il dettaglio mostra ora un confronto prima/dopo leggibile invece del JSON grezzo.

### Fixed

- Aggiunti `lang/it/auth.php` e `lang/it/passwords.php`, assenti di default in Laravel 12: i messaggi di errore di login/reset password mostravano la chiave di traduzione grezza (es. "auth.failed") invece di un testo leggibile.
- `reset-password.blade.php` inviava il form alla route sbagliata (`password.update`, autenticata, invece di `password.store`, per il link via email): il reset password da email non poteva mai funzionare.
- `GroupController::update` (rinomina gruppo) non verificava che l'utente fosse capo: qualsiasi membro poteva rinominare il gruppo chiamando la route direttamente.
- `GroupController::updateRole` non impediva di rimuovere l'ultimo capo di un gruppo.
- Rimossa `auth/register.blade.php`, vista irraggiungibile (nessuna rotta di registrazione: gli account si creano solo su invito).

### Removed

- Sezione "Token API" nel profilo nascosta (nessun client la usa: l'app mobile ottiene il proprio token da `POST /api/login`); route/controller restano attivi per un uso futuro.

## [v1.0.0] - 2026-09-09

### Added

- **E1** — Rinnovo di tutte le scadenze per selezione (tagliando, cinghia, ministeriale, ossigeno, ferie): ogni rinnovo crea un nuovo record preservando lo storico.
- **E2** — La cinghia ora si rinnova per selezione (non più per `activity_type`) con chilometraggio obbligatorio, come i tagliandi.
- **E3** — Rilevamento conflitti: gli appuntamenti sovrapposti per lo stesso veicolo vengono rifiutati con messaggio dedicato.
- **E4** — Sezione guasti risolti nascosta quando non ci sono guasti risolti da collegare all'appuntamento.
- **E5** — Badge versione nell'header, alimentato da `config('app.version')`.

### Fixed

- **B1** — `syncStatusFromRules()` ora preserva lo stato delle scadenze marcate come rinnovate (`is_renewed = true`), aggiungendo un early return.
- **B2** — `getAutomaticStatusAttribute()` e `syncStatusFromRules()` ora restituiscono `STATUS_VALID` (e non `STATUS_RENEWED`) per scadenze future fuori dal periodo di warning.
- **B3** — Allineato il default di `warning_months` a **3** (come il controller) in entrambi i punti del Model (`getAutomaticStatusAttribute()` e `syncStatusFromRules()`).
- **B4** — Aggiunto accessor `getMileageAttribute()` su `Vehicle` per restituire l'ultimo chilometraggio registrato dal log.
- **B5** — `VehicleController::update()` ora gestisce il caricamento della carta di circolazione (mancava rispetto a `store()`).
- **B6** — Sostituito `type="number"` con `type="text" inputmode="numeric"` per `internal_code` in create ed edit, per evitare perdita di zeri iniziali.

### Refactoring

- **D1** — Estratta logica di sorting/grouping in trait `SortableAndGroupable`. Refactorati `DeadlineController`, `IssueController`, `MaintenanceRecordController` e relative view.
- **D2** — Estratta logica di rilevamento duplicati in trait `DetectsDuplicates`. Refactorati `IssueController`, `MaintenanceRecordController`, `ProviderController`.
- **D3** — Estratto calcolo estensione garanzia in trait `HandlesWarrantyExtension`. Refactorati `StoreVehicleRequest`, `UpdateVehicleRequest` e `VehicleController`.
- **D4** — Rimosso `resolveStatus()` dal `DeadlineController`. Ora lo stato viene calcolato da `syncStatusFromRules()` nel Model, eliminando la duplicazione di logica.

### Performance

- **M1** — `applySorting()` ora accetta mappa con colonne DB (orderBy diretto) o callable (sorting in memoria). Ordinamento DB per `status`, `event_date`, `appointment_date`. Refactorati `IssueController` e `MaintenanceRecordController`.
- **M2** — Aggiunta paginazione (20 per pagina) in VehicleController, ProviderController, EquipmentController, MileageLogController, VehicleTypeController, EquipmentTypeController. Aggiunto supporto `paginator` al componente `x-admin.index-table`.

### Migliorie

- **M3** — Aggiunta validazione `unique` su `serial_number` in `StoreEquipmentRequest` e `UpdateEquipmentRequest`.
- **M4** — Aggiunta validazione `unique` su `name` in `StoreProviderRequest` e `UpdateProviderRequest` + migration per rendere `name` unico nel DB.
- **M5** — Aggiunto SoftDeletes a Vehicle, Issue, MaintenanceRecord, Deadline. Creata migration unica `add_soft_deletes_to_related_tables`.
- **M6** — Aggiunta colonna `role` a users (default `worker`). Create 9 Policy con trait `HasRoleBasedAccess`. Aggiunto trait `AdminOnlyAccess` a tutti i 16 FormRequest. Registrate tutte le Policy in `AppServiceProvider`.
- **M7** — Aggiunta validazione `after_or_equal:immatricolation_date` su `warranty_expiration_date` in `StoreVehicleRequest` e `UpdateVehicleRequest`.
- **M8** — Aggiunta validazione `withValidator` su `mileage` in `StoreMileageLogRequest` e `UpdateMileageLogRequest`: il nuovo chilometraggio non può essere inferiore all'ultimo registrato per lo stesso veicolo.
- **M9** — Aggiunto script JavaScript per il theme toggle (chiaro/scuro) con salvataggio in localStorage.

### Feature

- **F1** — Implementata dashboard interattiva con statistiche, scadenze imminenti, guasti aperti, prossimi appuntamenti, equipaggiamento e veicoli da attenzionare. Supporto dark mode.
- **F2** — Implementato sistema di notifiche email: comando `SendSummaryReport`, Mailable `ReportMail`, scheduler configurabile (daily/weekly/monthly), model `NotificationSetting` con CRUD per impostare email destinatario, frequenza e giorni reminder.
- **F3** — Implementato export PDF scheda veicolo con DomPDF. Controller `PdfExportController`, view con anagrafica, scadenze, guasti, dotazioni, manutenzioni e storico. Bottone "Scarica PDF" nella show del veicolo. Aggiunti accessor `status_color` su Issue, Deadline, Equipment e `open_issues` su Vehicle.
- **F4** — Implementato audit log con `spatie/laravel-activitylog`. Tracciamento automatico di creazione, modifica ed eliminazione su Vehicle, Issue, MaintenanceRecord, Deadline, Equipment. Solo modifiche effettive (`logOnlyDirty`).
