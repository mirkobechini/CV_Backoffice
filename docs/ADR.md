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
- Optional TOTP two-factor authentication (added later, see §13) builds on top of this Sanctum session login, not a separate auth system

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

## 13. Optional TOTP two-factor authentication

### Decision

Any user can enable TOTP-based two-factor authentication from their profile, built on **pragmarx/google2fa** (secret generation/verification) + **bacon/bacon-qr-code** (renders the activation QR as inline SVG), not Laravel Fortify. Fortify replaces the entire auth scaffolding with its own actions/routes; this project uses Breeze's own controllers, so adding 2FA as a thin layer on top of the existing `LoginRequest`/`AuthenticatedSessionController` was less invasive than migrating off Breeze.

Flow: enabling generates an unconfirmed secret (`two_factor_secret`, `encrypted` cast) shown as a QR + manual key; confirming a code sets `two_factor_confirmed_at` (the only "is 2FA on" signal — `User::hasTwoFactorEnabled()`) and generates 8 single-use recovery codes (`two_factor_recovery_codes`, `encrypted:array`, shown once). At login, `LoginRequest::authenticate()` validates credentials via `Auth::getProvider()` directly (not `Auth::attempt()`, which would log the user in immediately) and, if 2FA is enabled, stores the pending user id in session instead of calling `Auth::login()`; a separate `TwoFactorChallengeController` (code or recovery code) completes it.

### Rationale

- TOTP's HMAC-based algorithm (RFC 6238) is easy to get subtly wrong by hand; pragmarx/google2fa is the de facto standard for this on Laravel, with no external service/SMS cost
- Optional for every user (not just capo/sottocapo) rather than mandatory: capo/sottocapo instead get a dashboard banner recommending it, since forcing it on existing accounts at release time would lock people out without warning
- Recovery codes ratchet per-code consumption (`User::redeemRecoveryCode()`), not a single "used" flag, so a lost-device scenario has 8 independent fallbacks rather than one

### Consequences

- The 2FA challenge route sits under `guest` middleware (the user isn't logged in yet) with its own rate limiter (`two-factor`, 5/min keyed by pending user id + IP) — a TOTP code only has 10^6 combinations, worth throttling specifically rather than relying on the generic login limiter
- `two_factor_secret`/`two_factor_recovery_codes` are `encrypted`-cast: a raw DB dump (or the JSON backup from §2) never exposes them in plaintext
- No recovery path if a user loses both their authenticator app and all recovery codes — account recovery in that case is a manual DB intervention (disable via `php artisan tinker` or direct update), same as any self-hosted TOTP setup without a support team behind it

---

## 14. Telegram bot as a second notification channel

### Decision

Any user can link a Telegram account from **Impostazioni → Notifiche**, and from then on receives the same events `app:generate-notifications` already emails (gated by the same `notify_on_*` toggles) over Telegram too. No SDK: `TelegramNotifier` calls the Bot API directly over HTTP (`Http::post(".../sendMessage", ...)`) — the API surface used here (`sendMessage`, webhook updates) is small enough that a library would add a dependency without saving meaningful code.

Linking works via a one-time code rather than, say, asking for a phone number: the user generates a code from the profile, sends `/start CODE` to the bot in Telegram, and a webhook (`POST /telegram/webhook`, no session — verified via Telegram's `secret_token` header) matches the code to the pending `NotificationSetting` row and stores the resulting `chat_id` against that user. `/stop` unlinks. The webhook has no other commands; it isn't a conversational bot.

### Rationale

- Volunteer associations coordinate more over Telegram/WhatsApp groups than email day-to-day; Telegram was chosen over WhatsApp Business API specifically because it's free, has no per-message cost, and setup is a single bot token from @BotFather instead of Meta business verification
- A linking code (not a phone number or email match) is the only way for the bot to learn *whose* Telegram chat is writing to it — Telegram's API gives the bot a `chat_id` per conversation, with no inherent link to an app account
- Reusing `NotificationSetting` (already the per-user key/value store for `report_email`, `reminder_days_before`, etc.) for `telegram_chat_id`/`telegram_link_token` avoided a new table for two rows per user

### Consequences

- The webhook route carries no CSRF/session, same category as the public fleet status page (§ public routes) — security is the `secret_token` header check plus its own rate limiter (`telegram-webhook`, 60/min by IP)
- Telegram sending is gated by the same `--email` flag as email (effectively "send external notifications"), not a separate flag — the two channels are dispatched from the same per-user event list collected during the command's single pass over users
- No message queue: `sendMessage` calls happen synchronously inside the scheduled command, same as the existing `Mail::send()` calls right next to them — acceptable at this fleet's notification volume, would need revisiting if it ever grew enough to matter

---

## 15. Vehicle reliability trend, not a failure prediction

### Decision

The vehicle show page shows a "Tendenza riparazioni" card for unscheduled repairs (`MaintenanceRecord::ACTIVITY_REPAIR`) only: the average interval between a vehicle's past repairs, and whether the most recent interval is markedly shorter than its own prior average (`VehicleReliabilityTrendService`). Hidden entirely below 3 historical repairs; the worsening comparison needs a 4th.

This directly replaces the original roadmap idea of a per-component failure prediction (docs/ROADMAP_IDEE.md §6), dropped after discussion: `Issue.description` is free text, not categorized by component, so grouping "engine faults" vs "brake faults" isn't reliably possible without a new structured field that doesn't exist yet.

### Rationale

- Scheduled maintenance (Tagliando, Revisione, Cinghia) already has an exact next-due-date from `Deadline`'s fixed intervals — a statistical estimate there would be redundant, not predictive
- An unscheduled repair has no fixed interval by definition, so "predicting the next failure date" overclaims precision the data can't support; a trend signal ("repairs happening faster than this vehicle's own history") is the honest version of the same idea
- The worsening threshold (last interval ≤ 60% of the prior average) is a deliberately blunt cutoff, chosen to avoid flagging normal month-to-month noise on a small fleet's sparse repair history as a mandate; the comment on `WORSENING_RATIO_THRESHOLD` carries this rationale for whoever retunes it later

### Consequences

- A low-effort path back toward the original per-component idea is noted in docs/ROADMAP_IDEE.md §6: an optional `category` field on `Issue`, filled in gradually going forward, no backfill of existing free-text descriptions needed
- Scoped to the vehicle's own page only, not the dashboard — a fleet-wide "vehicles getting worse" view was considered but deferred, since the per-vehicle signal needed validating first

---

## References

- [Laravel SoftDeletes documentation](https://laravel.com/docs/11/eloquent#soft-deleting)
- [Laravel Sanctum](https://laravel.com/docs/11/sanctum)
- [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
- [DomPDF](https://github.com/barryvdh/laravel-dompdf)
- [FullCalendar](https://fullcalendar.io/)
