# ADR — Decisioni architetturali di CV Backoffice

## Stato

✅ Adottato

## Contesto

CV Backoffice è un'applicazione web per la gestione centralizzata di una flotta di mezzi di pubblica assistenza. Durante lo sviluppo sono state prese diverse decisioni architetturali che è utile documentare in un unico documento di riferimento.

---

## 1. Collegamento polimorfico guasti/scadenze ↔ appuntamenti officina

### Decisione

Abbiamo usato una relazione **morphMany** (`maintenance_record_items`) per collegare `MaintenanceRecord` (appuntamento officina) con `Issue` (guasti) e `Deadline` (scadenze). La tabella `maintenance_record_items` ha i campi `itemable_id` e `itemable_type`.

### Motivazione

- Un appuntamento in officina può coinvolgere sia un guasto che una scadenza (es. tagliando)
- La relazione polimorfica evita di duplicare colonne nullable (`issue_id`, `deadline_id`)
- Permette di estendere facilmente ad altre entità future

### Conseguenze

- `MaintenanceRecord` non ha più FK dirette a `issues` o `deadlines`
- Le query richiedono `with('items.itemable')` per il caricamento eager

---

## 2. SoftDeletes su veicoli, guasti, scadenze, manutenzioni, fornitori

### Decisione

Abbiamo applicato `SoftDeletes` (Laravel) su `Vehicle`, `Issue`, `Deadline`, `MaintenanceRecord` e `Provider`. I record cancellati rimangono nel DB con `deleted_at` valorizzato.

### Motivazione

- Dati sensibili: non vogliamo perdere storico guasti e manutenzioni
- Integrità referenziale: le relazioni esistenti non si rompono alla cancellazione
- Recupero facile in caso di errore

### Conseguenze

- Tutte le query usano automaticamente `WHERE deleted_at IS NULL`
- Per includere cancellati serve `withTrashed()`
- Le view e report possono accedere anche allo storico cancellato

---

## 3. Stato automatico delle scadenze (data + km)

### Decisione

Lo stato di una scadenza (`pending`, `valid`, `expired`, `renewed`) viene calcolato automaticamente tramite l'accessor `getAutomaticStatusAttribute()` sul modello `Deadline`, basato su:
- **Data**: `due_date` confrontata con oggi + finestra di warning (`DEADLINE_WARNING_MONTHS`)
- **Chilometraggio**: `last_mileage + interval_km` confrontato con l'ultimo km registrato

### Motivazione

- Stato sempre aggiornato senza bisogno di cron job o aggiornamenti manuali
- Integrazione con chilometraggi: scadenze come tagliando e cinghia distribuzione dipendono dai km
- L'accessor Eloquent ricalcola ogni volta, garantendo freschezza

### Conseguenze

- `is_renewed` è l'unico flag manuale e prevale sull'auto-calcolo
- `loadMissing('vehicle.latestMileageLog')` necessario per evitare N+1
- `deadlines.warning_months` configurabile via `.env` (default: 3 mesi)

---

## 4. Autenticazione con Sanctum e ruoli

### Decisione

Abbiamo usato **Laravel Sanctum** per:
- Autenticazione web (sessioni) per il backoffice
- API token per 11 endpoint REST (consumati da app esterne)

I ruoli (`admin`, `manager`, `worker`, `volunteer`) sono gestiti tramite **Laravel Policies**.

### Motivazione

- Sanctum è nativo Laravel, zero dipendenze esterne
- Unico pacchetto per sessioni + API token
- Leggero rispetto a Passport / Jetstream
- Le policy permettono autorizzazione granulare senza introdurre pacchetti esterni

### Conseguenze

- `RegisteredUserController` blocca la registrazione pubblica (solo admin può creare utenti)
- Il primo utente diventa automaticamente admin (comando `php artisan make:admin`)
- Rate limiting: 30 req/min per route admin, 5 req/min per login

---

## 5. Notifiche email con scheduler

### Decisione

Abbiamo un comando Artisan `app:send-summary-report` schedulato in `routes/console.php` che invia un report riassuntivo via email (Laravel Mail + `Mailpit/SMTP`).

### Motivazione

- Report periodici configurabili (daily/weekly/monthly) senza servizi esterni
- Configurazione via tabella `notification_settings` modificabile dalla UI
- Lo scheduler di Laravel gestisce la cadenza senza cron job manuali

### Conseguenze

- Richiede che lo scheduler sia attivo (`php artisan schedule:run` ogni minuto sul server)
- Le email usano `ReportMail` (Mailable) con template Blade

---

## 6. Export PDF e CSV

### Decisione

- **PDF**: DomPDF per la scheda veicolo (`PdfExportController@vehiclePdf`)
- **CSV**: Export generico per 7 entità (`CsvExportController@export`)

### Motivazione

- DomPDF non richiede browser headless (leggero, funziona su hosting basic)
- CSV con stream response per gestire volumi di dati senza memory leak

---

## 7. Cache dashboard

### Decisione

`DashboardController@index` usa `Cache::remember()` con TTL 5 minuti.

### Motivazione

- La dashboard esegue 6+ query (conteggi, scadenze, guasti aperti) su dati che cambiano raramente
- Riduce il carico sul DB nelle ore di picco

---

## 8. Audit logging con spatie/laravel-activitylog

### Decisione

Abbiamo usato il pacchetto `spatie/laravel-activitylog` su `Vehicle`, `Issue`, `Deadline`, `MaintenanceRecord`, `MileageLog`, `Equipment`. La UI del registro attività è in `admin/activity-log`.

### Motivazione

- Conformità: tracciare chi ha fatto cosa su dati sensibili (mezzi di pubblica assistenza)
- `logAll()` + `logOnlyDirty()` registra solo le modifiche effettive
- La UI con filtri (per utente, entità, data) rende consultabile lo storico

### Conseguenze

- Ogni modello con audit ha il trait `LogsActivity` e il metodo `getActivitylogOptions()`
- La tabella `activity_log` può crescere rapidamente — tenerlo monitorato

---

## 9. Ricerca testuale (Searchable trait)

### Decisione

Abbiamo un trait `Searchable` con scope `search()` che:
- Su **MySQL/MariaDB**: usa `FULLTEXT MATCH` per colonne lunghe (`issues.description`)
- Su **SQLite**: usa `LIKE %term%` come fallback

### Motivazione

- Uniformità tra ambienti di sviluppo (SQLite) e produzione (MySQL)
- FULLTEXT scalabile su volumi elevati senza modificare i controller
- Ogni modello dichiara `$searchable` e opzionalmente `$fulltextable`

---

## Riferimenti

- [Documentazione Laravel SoftDeletes](https://laravel.com/docs/11/eloquent#soft-deleting)
- [Laravel Sanctum](https://laravel.com/docs/11/sanctum)
- [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
- [DomPDF](https://github.com/barryvdh/laravel-dompdf)
- [FullCalendar](https://fullcalendar.io/)