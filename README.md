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
    reading integer Lettura contachilometri,
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

- Issue 0/1 : 1 MaintenanceRecord
  Un intervento di manutenzione può nascere da un guasto specifico.


## Legenda
✅ completato
🔄 in corso
⏳ in attesa/bloccato
⬜ da fare

## Todos:
### Setup ✅
- [X] Install laravel
- [X] Install breeze
- [X] Set database in .env
- [X] Set storage filesystem in .env
- [X] Set storage symlink

### Database 🔄
- [X] Vehicle model & migration
- [X] Provider model & migration
- [X] Issue model & migration
- [X] MaintenanceRecord model & migration
- [] Deadline model & migration
- [] MileageLog model & migration
- [] Equipment model & migration
- [] 1xN
- [] NxN
- [] Pivot table
- [X] Vehicle seed
- [X] Provider seed
- [] Issue seed
- [] MaintenanceRecord seed
- [] Deadline seed
- [] MileageLog seed
- [] Equipment seed

### UI 🔄
#### Commons 🔄
- [X] partials/Header
- [X] layouts/app
- [] welcomePage


#### Vehicle ⏳
- [X] index
- [X] show
- [X] create
- [/] store
- [X] edit
- [/] update
- [X] destroy

#### Provider ✅
- [X] index
- [X] show
- [X] create
- [X] store
- [X] edit
- [X] update
- [X] destroy

#### Issue 🔄
- [X] index
- [] show
- [] create
- [] store
- [] edit
- [] update
- [] destroy

#### MaintenanceRecord 🔄
- [X] index
- [] show
- [] create
- [] store
- [] edit
- [] update
- [] destroy

#### Deadline ⬜
- [] index
- [] show
- [] create
- [] store
- [] edit
- [] update
- [] destroy

#### MileageLog ⬜
- [] index
- [] show
- [] create
- [] store
- [] edit
- [] update
- [] destroy

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
- [/] admin/VehicleController (CRUD)
- [X] admin/ProviderController (CRUD)
- [] admin/IssueController (CRUD)
- [] admin/MaintenanceRecordController (CRUD)
- [] admin/DeadlineController (CRUD)
- [] admin/MileageLogController (CRUD)
- [] admin/EquipmentController (CRUD)

#### Routes 🔄
- [X] admin/VehicleController route (web)
- [X] admin/ProviderController route (web)
- [X] admin/IssueController route (web)
- [X] admin/MaintenanceRecordController route (web)
- [] admin/DeadlineController route (web)
- [] admin/MileageLogController route (web)
- [] admin/EquipmentController route (web)

### API ⬜
#### Controllers ⬜
- [] api/VehicleController (R)
- [] api/ProviderController (R)
- [] api/IssueController (R)
- [] api/MaintenanceRecordController (R)
- [] api/DeadlineController (R)
- [] api/MileageLogController (R)
- [] api/EquipmentController (R)

#### Routes ⬜
- [] api/VehicleController route (api)
- [] api/ProviderController route (api)
- [] api/IssueController route (api)
- [] api/MaintenanceRecordController route (api)
- [] api/DeadlineController route (api)
- [] api/MileageLogController route (api)
- [] api/EquipmentController route (api)