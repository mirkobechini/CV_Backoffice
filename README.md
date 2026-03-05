# 🚑 CV Backoffice - Gestione Parco Mezzi

Applicazione Laravel per la gestione di mezzi, guasti, manutenzioni, scadenze, chilometraggi e dotazioni di bordo.

## Panoramica

Il progetto copre:

- anagrafiche mezzi e tipologie mezzo
- gestione guasti e interventi manutentivi
- gestione scadenze periodiche
- gestione chilometraggi
- gestione attrezzature e tipologie attrezzature

## Stack

- Laravel (Breeze)
- PHP + Eloquent ORM
- Blade
- MySQL/MariaDB (supportato anche SQLite per sviluppo)

## Avvio rapido

1. Installazione dipendenze

- `composer install`
- `npm install`

2. Configurazione ambiente

- copia `.env.example` in `.env`
- configura DB e filesystem
- `php artisan key:generate`

3. Database

- `php artisan migrate`
- `php artisan db:seed`

4. Storage + frontend

- `php artisan storage:link`
- `npm run dev`

## Modello dati (stato attuale)

### Vehicle

- `license_plate` string(7) unique
- `internal_code` string(4) nullable
- `brand`, `model`
- `fuel_type` enum nullable
- `vehicle_type_id` foreignId nullable
- `immatricolation_date` date
- `registration_card_path` nullable
- `has_warranty_extension` boolean
- `warranty_extension_duration` integer nullable
- `warranty_expiration_date` date nullable

### VehicleType

- `name` unique
- `needs_oxygen_check` boolean
- `extinguishers_required` integer
- `first_inspection_months` integer
- `regular_inspection_months` integer

### Provider

- `name`
- `contact_info` nullable
- `address` nullable
- `type` enum (`Meccanico`, `Carrozziere`, `Gommista`, `Lavaggio`, `Allestitore`)

### Issue

- `vehicle_id`
- `description` text
- `status` enum (`open`, `in_progress`, `closed`)
- `photo` nullable
- `event_date` date

### MaintenanceRecord

- tabella attuale: `maintenance_records`
- `vehicle_id`, `provider_id`, `issue_id`
- `deadline_id` nullable unique
- `appointment_date` date
- `return_date` date nullable
- `activity_type` string nullable

### Deadline

- `vehicle_id`
- `type` enum (`Assicurazione`, `Revisione Ministeriale`, `Revisione Impianto Ossigeno`)
- `due_date` date
- `status` enum (`expired`, `renewed`, `pending`)

### MileageLog

- `vehicle_id`
- `log_date` date
- `mileage` unsigned integer

### Equipment

- `vehicle_id` nullable (`nullOnDelete`)
- `equipment_type_id`
- `name`
- `serial_number` unique nullable
- `revision_date` nullable
- `expiration_date` nullable

### EquipmentType

- `name` unique
- `first_inspection_months` nullable
- `regular_inspection_months` nullable

## Relazioni principali

- Vehicle `1:N` Issue
- Vehicle `1:N` Deadline
- Vehicle `1:N` MileageLog
- Vehicle `1:N` Equipment
- Vehicle `1:N` MaintenanceRecord
- Provider `1:N` MaintenanceRecord
- Issue `1:N` MaintenanceRecord
- Deadline `1:1` (opzionale) MaintenanceRecord
- EquipmentType `1:N` Equipment

## Convenzioni Laravel

Allineamento automatismi Eloquent:

- `equipment_type_id` usato come FK standard
- `maintenance_records` usato come nome tabella convenzionale
- relation methods e mass-assignment allineati nei controller CRUD

Nota storica:

- alcune migration meno recenti mantengono nomi file legacy (`maintenancerecords`) ma lo schema runtime è coerente.

## Stato attività (TODO)

Legenda:

- ✅ completato
- 🔄 in corso
- ⏳ in attesa/bloccato
- ⬜ da fare

### Setup ✅

- [x] Install Laravel
- [x] Install Breeze
- [x] Configurare database in `.env`
- [x] Configurare filesystem in `.env`
- [x] Creare symlink storage

### Database 🔄

- [x] Vehicle model + migration
- [x] VehicleType model + migration
- [x] Provider model + migration
- [x] Issue model + migration
- [x] MaintenanceRecord model + migration
- [x] Deadline model + migration
- [x] MileageLog model + migration
- [x] Equipment model + migration
- [x] EquipmentType model + migration
- [ ] Rivedere VehicleType per gestione equipaggiamento per tipologia mezzo
- [x] Vehicle seed
- [x] VehicleType seed
- [x] Provider seed
- [x] Issue seed
- [ ] MaintenanceRecord seed
- [ ] Deadline seed
- [ ] MileageLog seed
- [ ] Equipment seed
- [ ] EquipmentType seed

### UI ⏳

#### Commons ⏳

- [x] Header
- [x] Layout app
- [ ] Welcome page

#### Vehicle 🔄

- [x] index
- [x] show
- [x] create
- [x] edit
- [ ] show: link agli equipaggiamenti del mezzo + stato conformità per equipaggiamento

#### VehicleType ✅

- [x] index
- [x] show
- [x] create
- [x] edit

#### Provider ✅

- [x] index
- [x] show
- [x] create
- [x] edit

#### Issue ✅

- [x] index
- [x] show
- [x] create
- [x] edit
- [x] show: pulsante prenotazione manutenzione dal guasto
- [x] validazione: errore se `appointment_date < event_date`

#### MaintenanceRecord ✅

- [x] index
- [x] show
- [x] create/store
- [x] edit

#### Deadline 🔄

- [x] index
- [x] show
- [x] create
- [x] edit
- [ ] implementare tagliandi in scadenze: oltre alla data, soglia km per prossimo tagliando

#### MileageLog 🔄

- [x] index
- [x] show
- [x] create
- [x] edit
- [ ] report storico chilometri per singolo mezzo (timeline)
- [ ] report ultimo chilometraggio per mese (singolo mezzo)
- [ ] filtro per mezzo + range mese/anno

#### Equipment ✅

- [x] index
- [x] show
- [x] create
- [x] edit

#### EquipmentType ✅

- [x] index
- [x] show
- [x] create
- [x] edit

### Admin ✅

#### Controllers

- [x] Vehicle
- [x] VehicleType
- [x] Provider
- [x] Issue
- [x] MaintenanceRecord
- [x] Deadline
- [x] MileageLog
- [x] Equipment
- [x] EquipmentType

#### Routes (web)

- [x] Vehicle
- [x] VehicleType
- [x] Provider
- [x] Issue
- [x] MaintenanceRecord
- [x] Deadline
- [x] MileageLog
- [x] Equipment
- [x] EquipmentType

#### Best practices

- [x] Validazioni CRUD in FormRequest
- [x] Controller alleggeriti con `$request->validated()`
- [x] Normalizzazione dati Vehicle in request
- [x] Regole business MaintenanceRecord centralizzate nei request
- [x] Commenti nelle aree con logica complessa

### API ⬜

#### Controllers

- [ ] Vehicle
- [ ] VehicleType
- [ ] Provider
- [ ] Issue
- [ ] MaintenanceRecord
- [ ] Deadline
- [ ] MileageLog
- [ ] Equipment
- [ ] EquipmentType

#### Routes

- [ ] Route API Vehicle
- [ ] Route API VehicleType
- [ ] Route API Provider
- [ ] Route API Issue
- [ ] Route API MaintenanceRecord
- [ ] Route API Deadline
- [ ] Route API MileageLog
- [ ] Route API Equipment
- [ ] Route API EquipmentType
