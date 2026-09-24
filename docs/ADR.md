# ADR — CV Backoffice Architecture Decisions

## Status

✅ Adopted

## Context

CV Backoffice is a web application for the centralized management of a public-assistance vehicle fleet. Several architectural decisions were made during development that are worth documenting in a single reference document.

---

## 1. Polymorphic link between faults/deadlines ↔ workshop appointments

### Decision

We used a **morphMany** relation (`maintenance_record_items`) to link `MaintenanceRecord` (workshop appointment) with `Issue` (faults) and `Deadline` (deadlines). The `maintenance_record_items` table has the fields `itemable_id` and `itemable_type`.

### Rationale

- A workshop appointment can involve both a fault and a deadline (e.g. a service)
- The polymorphic relation avoids duplicating nullable columns (`issue_id`, `deadline_id`)
- It allows easy extension to other entities in the future

### Consequences

- `MaintenanceRecord` no longer has direct FKs to `issues` or `deadlines`
- Queries require `with('items.itemable')` for eager loading

---

## 2. SoftDeletes on vehicles, faults, deadlines, maintenance records, providers

### Decision

We applied `SoftDeletes` (Laravel) to `Vehicle`, `Issue`, `Deadline`, `MaintenanceRecord` and `Provider`. Deleted records remain in the DB with `deleted_at` set.

### Rationale

- Sensitive data: we don't want to lose the history of faults and maintenance
- Referential integrity: existing relations aren't broken on deletion
- Easy recovery in case of a mistake

### Consequences

- All queries automatically use `WHERE deleted_at IS NULL`
- `withTrashed()` is needed to include deleted records
- Views and reports can also access deleted history

---

## 3. Automatic deadline status (date + mileage)

### Decision

The status of a deadline (`pending`, `valid`, `expired`, `renewed`) is automatically computed via the `getAutomaticStatusAttribute()` accessor on the `Deadline` model, based on:
- **Date**: `due_date` compared with today plus a warning window (`DEADLINE_WARNING_MONTHS`)
- **Mileage**: `last_mileage + interval_km` compared with the latest recorded mileage

### Rationale

- Status is always up to date without cron jobs or manual updates
- Integration with mileage: deadlines such as service and timing belt depend on mileage
- The Eloquent accessor recalculates every time, guaranteeing freshness

### Consequences

- `is_renewed` is the only manual flag and overrides the auto-calculation
- `loadMissing('vehicle.latestMileageLog')` is needed to avoid N+1 queries
- `deadlines.warning_months` is configurable via `.env` (default: 3 months)

---

## 4. Authentication with Sanctum and roles

### Decision

We used **Laravel Sanctum** for:
- Web authentication (sessions) for the backoffice
- API tokens for 11 REST endpoints (consumed by external apps)

Roles (`admin`, `manager`, `worker`, `volunteer`) are managed via **Laravel Policies**.

### Rationale

- Sanctum is native to Laravel, zero external dependencies
- A single package for both sessions and API tokens
- Lightweight compared to Passport / Jetstream
- Policies allow granular authorization without introducing external packages

### Consequences

- No public registration route: accounts are only created by invitation (group code/email) or by a lead directly from the group page
- The first user automatically becomes admin (`php artisan make:admin` command)
- Rate limiting: 30 req/min for admin routes, 5 req/min for login

---

## 5. Email notifications with the scheduler

### Decision

We have an Artisan command `app:send-summary-report` scheduled in `routes/console.php` that sends a summary report via email (Laravel Mail + `Mailpit/SMTP`).

### Rationale

- Configurable periodic reports (daily/weekly/monthly) without external services
- Configuration via the `notification_settings` table, editable from the UI
- Laravel's scheduler handles the cadence without manual cron jobs

### Consequences

- Requires the scheduler to be active (`php artisan schedule:run` every minute on the server)
- Emails use `ReportMail` (Mailable) with a Blade template

---

## 6. PDF and CSV export

### Decision

- **PDF**: DomPDF for the vehicle info sheet (`PdfExportController@vehiclePdf`)
- **CSV**: generic export for 7 entities (`CsvExportController@export`)

### Rationale

- DomPDF doesn't require a headless browser (lightweight, works on basic hosting)
- CSV with a streamed response to handle large data volumes without memory leaks

---

## 7. Dashboard cache

### Decision

`DashboardController@index` uses `Cache::remember()` with a 5-minute TTL.

### Rationale

- The dashboard runs 6+ queries (counts, deadlines, open faults) on data that rarely changes
- Reduces DB load during peak hours

---

## 8. Audit logging with spatie/laravel-activitylog

### Decision

We used the `spatie/laravel-activitylog` package on `Vehicle`, `Issue`, `Deadline`, `MaintenanceRecord`, `MileageLog`, `Equipment`. The activity log UI is at `admin/activity-log`.

### Rationale

- Compliance: tracking who did what on sensitive data (public-assistance vehicles)
- `logAll()` + `logOnlyDirty()` records only actual changes
- The UI with filters (by user, entity, date) makes the history easy to consult

### Consequences

- Every audited model has the `LogsActivity` trait and the `getActivitylogOptions()` method
- The `activity_log` table can grow quickly — keep it monitored

---

## 9. Full-text search (Searchable trait)

### Decision

We have a `Searchable` trait with a `search()` scope that:
- On **MySQL/MariaDB**: uses `FULLTEXT MATCH` for long columns (`issues.description`)
- On **SQLite**: uses `LIKE %term%` as a fallback

### Rationale

- Consistency between development (SQLite) and production (MySQL) environments
- FULLTEXT scales on large volumes without changing the controllers
- Every model declares `$searchable` and, optionally, `$fulltextable`

---

## References

- [Laravel SoftDeletes documentation](https://laravel.com/docs/11/eloquent#soft-deleting)
- [Laravel Sanctum](https://laravel.com/docs/11/sanctum)
- [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
- [DomPDF](https://github.com/barryvdh/laravel-dompdf)
- [FullCalendar](https://fullcalendar.io/)
