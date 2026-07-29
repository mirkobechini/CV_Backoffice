# Changelog

Tutte le modifiche significative a questo progetto saranno documentate in questo file.

## [Unreleased]

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
