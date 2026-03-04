# 🛠 Database Schema - Gestione Parco Mezzi

Questo documento descrive la struttura del database, le entità principali e le loro relazioni.

## 1. Entità Principali (Anagrafica)

### Vehicle (Mezzo): Il cuore del sistema:

    license_plate string unique Targa,
    internal_code string nullable Sigla interna,
    brand string Marca,
    model string Modello,
    fuel_type string nullable Alimentazione,
    vehicle_type_id string Tipologia (es. ambulanza, auto),
    immatricolation_date string Data immatricolazione,
    registration_card_path string nullable Percorso file carta circolazione.
    warranty_original_expiration_date string nullable Data di scadenza originale della garanzia (per confronto).
    has_warranty_extension boolean default false Flag per estensione garanzia,
    warranty_expiration_date string nullable Data di scadenza della garanzia.

### VehicleType (Configurazione Categorie): Tabella di configurazione che definisce le regole di business per ogni categoria di mezzo.

    name string Nome della tipologia,
    needs_oxygen_check (booleano) per attivare la gestione impianto ossigeno.
    extinguishers_required (intero) per definire il numero minimo di estintori a bordo.
    first_inspection_months: intervallo per la prima revisione (es. 48 per auto, 12 per ambulanze).
    regular_inspection_months: intervallo per le successive (es. 24 per auto, 12 per ambulanze).

### Provider (Officina/Luogo): Fornitori esterni:

    name string Nome,
    contact_info string nullable Contatti (telefono, email),
    address string nullable Indirizzo/Posizione,
    type string Tipologia (Meccanico, Carrozziere, Gommista, Lavaggio, Allestitore).

## 2. Eventi e Manutenzione

### Issue (Guasto): Segnalazione di un problema tecnico:

    description string Descrizione,
    status string (open, in_progress, closed) Stato del guasto.
    photo string nullable Foto del guasto,
    event_date string default CURRENT_DATE Data dell'avvenimento.

    Relazione: Appartiene a un Vehicle

### MaintenanceRecord (Appuntamento/Intervento): Tracciamento dei lavori effettuati o programmati:

    appointment_date string Data appuntamento,
    return_date string nullable Data restituzione (per disponibilità mezzo),
    activity_type string Tipo attività (Tagliando, Revisione, Riparazione, Lavaggio).

    Relazione: Collega un Vehicle con un Provider. Può essere collegato a una specifica Issue.

## 3. Monitoraggio e Scadenze

### Deadline (Scadenza): Gestione di tutte le date critiche ricorrenti:

    type string Tipologia (Assicurazione, Revisione Ministeriale, Revisione Impianto Ossigeno),
    due_date string Data scadenza,
    status string (expired, renewed, pending) Stato.

    Relazione: Ogni record è collegato a un Vehicle.

### MileageLog (Registro Chilometri): Storico dei chilometri per reportistica e avvisi:

    mileage integer Lettura contachilometri,
    log_date string Data rilevazione.
    Relazione: Molteplici record per ogni Vehicle.

### Equipment (Attrezzatura/Estintori): Oggetti specifici a bordo del mezzo:

    name string Nome,
    serial_number string nullable Codice Seriale,
    revision_date string nullable Data revisione,
    expiration_date string nullable Data scadenza.
    Relazione: Ogni attrezzatura è assegnata a un Vehicle.

## 🔗 Relazioni (Entity-Relationship)

- Vehicle 1 : N Issue
  Un mezzo può avere più guasti nel tempo.

- Vehicle 1 : N Deadline
  Un mezzo ha diverse scadenze (Assicurazione, Revisione, ecc.).

- Vehicle 1 : N MileageLog
  Storico periodico dei chilometri per ogni mezzo.

- Vehicle 1 : N Equipment
  Un mezzo può avere più estintori o dotazioni specifiche.

- Provider 1 : N MaintenanceRecord
  Un'officina può gestire più appuntamenti/lavori.

- Vehicle 1 : N MaintenanceRecord
  Un mezzo entra in manutenzione più volte.

- Issue 1 : N MaintenanceRecord
  Un intervento di manutenzione può nascere da un guasto specifico.

## Legenda

✅ completato
🔄 in corso
⏳ in attesa/bloccato
⬜ da fare

## Todos:

### Setup ✅

- [x] Install laravel
- [x] Install breeze
- [x] Set database in .env
- [x] Set storage filesystem in .env
- [x] Set storage symlink

### Database 🔄

- [x] Vehicle model & migration
- [x] Provider model & migration
- [x] Issue model & migration
- [x] MaintenanceRecord model & migration
- [x] Deadline model & migration
- [x] MileageLog model & migration
- [] Equipment model & migration
- [x] Vehicle seed
- [x] Provider seed
- [x] Issue seed
- [] MaintenanceRecord seed
- [] Deadline seed
- [] MileageLog seed
- [] Equipment seed

### UI ⏳

#### Commons ⏳

- [x] partials/Header
- [x] layouts/app
- [] welcomePage

#### Vehicle ✅

- [x] index
- [x] show
- [x] create
- [x] store
- [x] edit
- [x] update
- [x] destroy

#### VehicleType ✅

- [x] index
- [x] show
- [x] create
- [x] store
- [x] edit
- [x] update
- [x] destroy

#### Provider ✅

- [x] index
- [x] show
- [x] create
- [x] store
- [x] edit
- [x] update
- [x] destroy

#### Issue ✅

- [x] index
- [x] show
- [x] create
- [x] store
- [x] edit
- [x] update
- [x] destroy
- [x] show: pulsante per prendere appuntamento manutenzione dal guasto
- [x] validazione: errore se appointment_date < event_date del guasto

#### MaintenanceRecord ✅

- [x] index
- [x] show
- [x] create
- [x] store
- [x] edit
- [x] update
- [x] destroy

#### Deadline ✅

- [x] index
- [x] show
- [x] create
- [x] store
- [x] edit
- [x] update
- [x] destroy

#### MileageLog 🔄

- [x] index
- [x] show
- [x] create
- [x] store
- [x] edit
- [x] update
- [x] destroy
- [] report: storico chilometri per singolo mezzo (timeline ordinata per data)
- [] report: ultimo chilometraggio registrato per ogni mese (per singolo mezzo)
- [] report: filtro per mezzo + range mese/anno

#### Equipment ⬜

- [] index
- [] show
- [] create
- [] store
- [] edit
- [] update
- [] destroy

### Admin 🔄

#### Controllers 🔄

- [x] admin/VehicleController (CRUD)
- [x] admin/VehicleTypeController (CRUD)
- [x] admin/ProviderController (CRUD)
- [x] admin/IssueController (CRUD)
- [x] admin/MaintenanceRecordController (CRUD)
- [x] admin/DeadlineController (CRUD)
- [x] admin/MileageLogController (CRUD)
- [] admin/EquipmentController (CRUD)

#### Routes 🔄

- [x] admin/VehicleController route (web)
- [x] admin/VehicleTypeController route (web)
- [x] admin/ProviderController route (web)
- [x] admin/IssueController route (web)
- [x] admin/MaintenanceRecordController route (web)
- [x] admin/DeadlineController route (web)
- [x] admin/MileageLogController route (web)
- [] admin/EquipmentController route (web)

#### Best Practices ✅

- [x] Refactor validazioni CRUD in FormRequest (Store/Update) per Vehicle, VehicleType, Provider, Issue, MaintenanceRecord, Deadline
- [x] Controller alleggeriti con uso di `$request->validated()`
- [x] Normalizzazione targa e checkbox garanzia spostata in `prepareForValidation()` dei Request Vehicle
- [x] Validazione business su MaintenanceRecord (appointment_date >= event_date del guasto) centralizzata nei Request
- [x] Commenti aggiunti nelle sezioni di logica complessa (deadline automation, observer, filtri/prefill create)

### API ⬜

#### Controllers ⬜

- [] api/VehicleController (R)
- [] api/VehicleTypeController (R)
- [] api/ProviderController (R)
- [] api/IssueController (R)
- [] api/MaintenanceRecordController (R)
- [] api/DeadlineController (R)
- [] api/MileageLogController (R)
- [] api/EquipmentController (R)

#### Routes ⬜

- [] api/VehicleController route (api)
- [] api/VehicleTypeController route (api)
- [] api/ProviderController route (api)
- [] api/IssueController route (api)
- [] api/MaintenanceRecordController route (api)
- [] api/DeadlineController route (api)
- [] api/MileageLogController route (api)
- [] api/EquipmentController route (api)
