# ADR — CV Backoffice Architecture Decisions

## Status

✅ Adopted

## Context

CV Backoffice is a web application for the centralized management of a public-assistance vehicle fleet. Several architectural decisions were made during development that are worth documenting in a single reference document.

---

## 1. Polymorphic link between faults/deadlines ↔ workshop appointments

### Decision

We used a **morphMany** relation (`maintenance_record_items`) to link `MaintenanceRecord` (workshop appointment) with `Issue` (faults), `Deadline` (deadlines) and `Tire` (a tire change: one or more tires linked to the same appointment, mounted together on completion). The `maintenance_record_items` table has the fields `itemable_id` and `itemable_type`.

Separately, a fault can also cover more than one tire directly (e.g. several tires punctured at once): `Issue` and `Tire` are linked by a plain **many-to-many pivot** (`issue_tire`), not through this polymorphic relation — the two relations serve different purposes and aren't related to each other.

### Rationale

- A workshop appointment can involve a fault, a deadline and/or a tire change together
- The polymorphic relation avoids duplicating nullable columns (`issue_id`, `deadline_id`, `tire_id`)
- It allows easy extension to other entities in the future
- The fault↔tire pivot is a simple many-to-many because it only needs to answer "which tires does this fault involve", with no extra per-pair data — a polymorphic relation would have been unnecessary complexity for that

### Consequences

- `MaintenanceRecord` no longer has direct FKs to `issues`, `deadlines` or `tires`
- Queries require `with('items.itemable')` for eager loading
- `Issue` no longer has a `tire_id` FK either; it exposes a `tires()` belongsToMany instead

---

## 2. SoftDeletes on vehicles, faults, deadlines, maintenance records, providers

### Decision

We applied `SoftDeletes` (Laravel) to `Vehicle`, `Issue`, `Deadline`, `MaintenanceRecord`, `Provider`, `EquipmentIssue` and `EquipmentMaintenanceRecord`. Deleted records remain in the DB with `deleted_at` set.

### Rationale

- Sensitive data: we don't want to lose the history of faults and maintenance
- Referential integrity: existing relations aren't broken on deletion
- Easy recovery in case of a mistake

### Consequences

- All queries automatically use `WHERE deleted_at IS NULL`
- `withTrashed()` is needed to include deleted records
- Views and reports can also access deleted history
- A soft-deleted record is easy to mistake for one that never existed, or for genuine data loss, when it simply isn't showing up where expected (found once investigating a deadline that turned out to not even exist — see §3's note on the sorting bug). `php artisan deadlines:inspect {vehicle}` is a read-only diagnostic for exactly this: lists every deadline of a vehicle including soft-deleted ones, with its activity log trail (who deleted it, when), and falls back to searching the activity log by model attributes for the rarer case of a row removed outside Eloquent (bypassing the soft delete entirely)

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
- Not every deadline has both a date and a mileage component: the timing belt deadline, for example, depends on `Vehicle.timing_belt_type` — a chain has no deadline at all, a dry belt is mileage-only (`due_date` stays null), and a belt running in an oil bath has both (100,000 km or 10 years, whichever comes first). `interval_km`/`last_mileage` and `due_date`/`interval_days` are independently nullable on `Deadline` for exactly this reason.
- Code that picks "the current deadline of this type for this vehicle" must not sort or deduplicate by `due_date`: a mileage-only deadline (dry belt) always has `due_date = null`, so when both the superseded and the renewed-from-it deadline share that same null value, a `due_date`-based ordering can't tell them apart and may silently pick the wrong one — this happened in production (`Vehicle::getDeadlinesGroupedAttribute()` and `DeadlineController::index()`'s default view both had this bug), making an active deadline appear to have vanished even though the row was never touched. `is_renewed` is the reliable signal for "superseded", independent of whether that deadline type has a time component at all.

---

## 4. Authentication with Sanctum and roles

### Decision

We used **Laravel Sanctum** for:
- Web authentication (sessions) for the backoffice
- API tokens for 12 REST endpoints (consumed by external apps)

Roles (`capo`/lead, `sottocapo`/deputy, `member`) are managed via **Laravel Policies**, scoped per group — see [§10, Group-based multi-tenancy](#10-group-based-multi-tenancy).

### Rationale

- Sanctum is native to Laravel, zero external dependencies
- A single package for both sessions and API tokens
- Lightweight compared to Passport / Jetstream
- Policies allow granular authorization without introducing external packages

### Consequences

- No public registration route: accounts are only created by invitation (group code/email) or by a lead directly from the group page
- The first user automatically becomes `capo`/lead of a new default group (`php artisan make:admin` command — named after the command's purpose, not the role)
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

- Requires the scheduler to actually run — on Laravel Cloud this means enabling the **Scheduler** toggle in the environment's App cluster settings (General tab), *not* a manually configured cron job; self-hosted deployments still need `php artisan schedule:run` every minute. See [docs/DEPLOY.md](DEPLOY.md). This was found misconfigured once already (the toggle was off), silently stopping both the summary report and `app:generate-notifications` — both commands now log to `storage/logs/laravel.log` on failure so a repeat is diagnosable instead of silent.
- Emails use `ReportMail` (Mailable) with a Blade template

---

## 6. PDF and CSV export

### Decision

- **PDF**: DomPDF for the per-vehicle info sheet (`PdfExportController@vehiclePdf`) and for a fleet-wide overview table — next deadlines and last mileage for every vehicle on one page (`PdfExportController@fleetOverview`, landscape A4)
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

We used the `spatie/laravel-activitylog` package on `Vehicle`, `Issue`, `Deadline`, `MaintenanceRecord`, `MileageLog`, `Equipment`, `EquipmentIssue`, `EquipmentMaintenanceRecord`. The activity log UI is at `admin/activity-log`.

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

## 10. Group-based multi-tenancy

### Decision

Every `Vehicle` belongs to a `Group` (an association/fleet owner), and every `User` belongs to one or more groups through the `group_user` pivot, with a per-group role (`capo`/lead, `sottocapo`/deputy, `member`). A user has one *active* group at a time, switchable from the UI. Data isolation is enforced primarily through model query scopes (`Vehicle::forCurrentUser()`, `::forGroup()`) applied consistently everywhere a group-owned record is read or written: admin controllers, Policies, the REST API, CSV/PDF export, the dashboard cache key, notifications and scheduled emails.

### Rationale

- The app serves multiple independent public-assistance associations from a single deployment; one association must never see another's vehicles, faults, deadlines or attachments
- Scoping at the query level (rather than relying on route/controller checks alone) means a new read path that forgets to scope fails closed in most cases, since the underlying query already excludes other groups' data — though every new query still needs the scope applied explicitly, this isn't automatic
- A role-less `capo`/lead is created automatically for the first account (`php artisan make:admin`); further users join via invite code or are added by a lead/deputy

### Consequences

- Unassigned resources (e.g. equipment not yet attached to a vehicle) have no group of their own and are deliberately treated as visible to everyone, which every related query has to account for explicitly (`whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($q) => $q->forGroup(...))`) — forgetting this clause excludes valid unassigned records instead of leaking others' data, a cheaper failure mode than the reverse
- Bulk-selection endpoints (e.g. multi-tire edit, multi-equipment revision) validate every selected ID against the current group via the `BelongsToCurrentUserGroup` rule or an equivalent query-based check, not a per-item `authorize()` call
- A record that can relate to *several* group-owned resources at once (an equipment appointment, which can involve more than one piece of equipment — see §12) doesn't have a single group in the general case. `EquipmentMaintenanceRecord`'s `vehicle` accessor (used by the same group-check Policies rely on everywhere else) returns the shared vehicle only when every linked equipment that has one points to the *same* vehicle, and `null` (permissive, same as an unassigned resource) only for the genuinely ambiguous case — spanning several groups, or none assigned at all. The first version of this accessor returned `null` unconditionally "because it can span groups", which silently disabled the group check even for the common single-vehicle case (a capo could open/edit/delete another group's equipment appointment directly by id, though list queries stayed correctly filtered) — found in a post-implementation audit, fixed before any release.
- This has been the source of real bugs more than once (data isolation bugs are listed across several commits) — any new cross-group query, or any new accessor a Policy relies on, needs to be scoped deliberately, it is not automatic

---

## 11. AI vision scan for vehicle registration documents

### Decision

A vehicle can be created (or an existing one updated) by uploading a photo of the front of the Italian libretto di circolazione; an LLM vision model (configurable provider, OpenRouter by default — see `config/services.php`) extracts the structured fields (plate, dates, brand/model, VIN, tire size, likely timing belt type, etc.) via `VehicleScanService`, which are then pre-filled into the create/edit form for the user to review before saving. Nothing is written to the database directly from the scan.

### Rationale

- Manually transcribing a dozen fields per vehicle from a photographed document is slow and error-prone; pre-filling and asking for confirmation is faster while keeping a human in the loop
- Using a configurable LLM vision provider avoids a hard dependency on one vendor and keeps the feature degradable: a missing/invalid API key or a failed call fails gracefully (flash message, manual entry still works), it never blocks vehicle creation
- The front photo is kept as the vehicle's registration card attachment if the scan or the subsequent save succeeds. A back photo was originally also required (the Italian libretto's periodic inspection stamps live there), but every field the system prompt actually extracts (`EXPECTED_FIELDS`) comes from standard codes printed on the front only — the back was being uploaded and sent to the vision model for nothing. Dropped: only the front is requested now.

### Consequences

- Scan failures are logged (`Log::warning`, since the PDF-report scheduler incident) with the real exception message, while the user only sees a generic "couldn't read it, enter manually" message
- The model can only ever suggest whether the vehicle likely has a timing belt vs. a chain (`has_timing_belt_suggested`) — it cannot tell a dry belt from an oil-bath one, that distinction isn't visible on the document and must be set manually
- Rate-limited separately (`throttle:vehicle-scan`) since each call costs an LLM API request
- The create/edit vehicle form shows the libretto code next to each technical field it can pre-fill (e.g. "VIN (E.)"), so a user filling it in by hand knows where to look on the document even without running the scan

---

## 12. Equipment faults and appointments: a simpler, non-polymorphic design

### Decision

Equipment (fire extinguishers, stretchers, DAE, etc.) can have faults (`EquipmentIssue`) and workshop appointments (`EquipmentMaintenanceRecord`) reported against it, mirroring the vehicle workflow (§1) but in a separate section of the UI and with a deliberately different, simpler data model:

- `EquipmentIssue` has a direct `equipment_id` FK (cascade) and an optional direct `equipment_maintenance_record_id` FK (nullable, `nullOnDelete`) to the appointment resolving it — no polymorphic `itemable` relation, because an equipment fault never needs to share a join table with other item types the way a vehicle appointment does with deadlines and tires.
- `EquipmentMaintenanceRecord` has **no** `vehicle_id` at all: unlike a vehicle appointment, which is always about exactly one vehicle, one equipment appointment can cover several pieces of equipment together (e.g. the same provider checking every fire extinguisher on the same day). The link is a plain many-to-many pivot, `equipment_maintenance_record_equipment`.
- Both models use `SoftDeletes` + `LogsActivity`, same as their vehicle counterparts (§2, §8).
- Authorization reuses the same `HasGroupScopedAccess` Policy trait as everything else (§10); see that section's note on the `EquipmentMaintenanceRecord.vehicle` accessor for the one genuinely new wrinkle this model introduces (a record that can span more than one group-owned resource).

### Rationale

- Vehicle appointments need the polymorphic `maintenance_record_items` table because one appointment can mix faults, deadlines *and* tires. Equipment appointments only ever need to relate to faults — reusing the same polymorphic machinery for a single item type would have been complexity with no payoff.
- A vehicle appointment's single-vehicle assumption doesn't hold for equipment: several items are routinely serviced together by the same provider on the same visit. Modeling that as a many-to-many from the start, instead of bolting it on later, avoids a second migration/refactor once the first multi-item appointment request came in (it came in during the same planning pass, before any code existed).
- Equipment deadlines (revision/collaudo intervals, §3's `Deadline`-adjacent-but-separate tracking on `Equipment`/`EquipmentRevision`) were explicitly kept out of this feature's scope — they already work and weren't part of what was missing.

### Consequences

- `EquipmentIssueController`/`EquipmentMaintenanceRecordController` don't need `with('items.itemable')`-style eager loading; they load `equipment`/`equipments`/`issues` directly, which is simpler but means the two feature pairs (vehicle vs equipment) don't share a common base controller or trait beyond `DetectsDuplicates`/`SortableAndGroupable`.
- Linking/unlinking an `EquipmentIssue` to an appointment, and reopening it when unlinked, is done with a per-model loop (not a mass `whereIn()->update()`) for the same reason already established for vehicle issues (§8): a bulk update bypasses `LogsActivity` entirely.
- Completing an equipment appointment only asks "were the linked faults resolved?" when there actually are linked faults — a pure revision/collaudo appointment with no fault involved doesn't force an answer to a question that doesn't apply to it.

---

## References

- [Laravel SoftDeletes documentation](https://laravel.com/docs/11/eloquent#soft-deleting)
- [Laravel Sanctum](https://laravel.com/docs/11/sanctum)
- [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
- [DomPDF](https://github.com/barryvdh/laravel-dompdf)
- [FullCalendar](https://fullcalendar.io/)
