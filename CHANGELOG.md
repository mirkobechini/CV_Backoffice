# Changelog

Tutte le modifiche significative a questo progetto saranno documentate in questo file.

## [Unreleased]

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
