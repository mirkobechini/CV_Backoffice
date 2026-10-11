# Manutenzione: cosa resta da fare

Elenco di quello che è rimasto aperto dopo il giro di aggiornamenti del 2026-10-11 (dipendenze, PHP, avvisi di sicurezza). Aggiornalo mentre chiudi i punti.

## Dove siamo

Già fatto, tutto sul branch `dev` (CI verde su PHP 8.4 e 8.5; `main` non è stato toccato):

- PHP minimo **8.4** (`composer.json` `^8.4`), CI su 8.4 e 8.5; produzione consigliata 8.5.
- Composer aggiornato entro i vincoli (Livewire 4.4, Symfony 8.1, Guzzle, Pint...), **PHPUnit 13** e **Laravel Tinker 3**: 669 test verdi, nessuna deprecazione.
- npm: aggiornamenti minori, `concurrently` 10 e override `postcss-selector-parser` ^7.1.6 (build CSS verificata identica al byte). Avvisi npm da 10 a 5, tutti solo dev.
- Test `ComposerLockPhpVersionTest`: fallisce se un `composer update` porta nel lock pacchetti che richiedono più PHP di quello dichiarato in `composer.json`.

## 1. Prima di portare `dev` su `main` (obbligatorio)

- [ ] **Laravel Cloud: impostare PHP 8.4 o 8.5 nell'ambiente** (General). Il lock richiede PHP >= 8.4.1: con 8.2/8.3 l'app non parte (errore di piattaforma di Composer). Vedi `docs/DEPLOY.md`.
- [ ] Spostare le voci di `[Unreleased]` del `CHANGELOG.md` sotto una nuova versione, come per le release precedenti.
- Nota sulla storia: `main` ha 58 commit `release: vX.Y.Z` che `dev` non ha. Sono solo merge commit: `git diff dev...main` è vuoto, quindi il contenuto è lo stesso e il merge `dev` -> `main` è normale (nessun conflitto atteso).

## 2. Laravel 13

Stato: provato, funziona. Il branch locale **`trial/laravel-13`** (non pubblicato su GitHub) contiene `laravel/framework ^13.0` con **669 test verdi** e nessuna deprecazione; la risoluzione delle dipendenze non ha conflitti (anche con `spatie/laravel-activitylog` 4.12.3). Se il branch è stato cancellato: `composer require laravel/framework:^13.0 -W`.

Da fare:
- [ ] Leggere la guida ufficiale di aggiornamento a Laravel 13 e applicare i punti che toccano questa app.
- [ ] Provare a mano le aree che i test non coprono bene: export PDF (DomPDF), 2FA (google2fa e QR), componenti Livewire, scheduler e notifiche Telegram, upload su R2 e URL firmati.
- [ ] Unire in `dev` e controllare la CI su 8.4 e 8.5 prima del merge su `main`.

## 3. `spatie/laravel-activitylog` 4 -> 5 (migrazione vera)

Stato: **non fattibile "al volo"**. Con la 5.x l'app si rompe subito: `Trait "Spatie\Activitylog\Traits\LogsActivity" not found` (il namespace è cambiato). La 4.12 funziona anche con Laravel 13, quindi non è urgente.

File da adattare (16):

- Modelli: `Deadline`, `Equipment`, `EquipmentIssue`, `EquipmentMaintenanceRecord`, `EquipmentRevision`, `Issue`, `MaintenanceRecord`, `MileageLog`, `Tire`, `TireChange`, `Vehicle`
- `app/Console/Commands/InspectVehicleDeadlines.php`, `app/Http/Controllers/Admin/ActivityLogController.php`
- `config/activitylog.php`
- Test: `tests/Feature/ActivityLogTest.php`, `tests/Feature/MaintenanceRecordBusinessLogicTest.php`

Da fare:
- [ ] Leggere le note di aggiornamento del pacchetto (nuovi nomi, config, eventuali migrazioni) e applicarle.
- [ ] Ripubblicare la config e confrontarla con `config/activitylog.php`.
- [ ] Attenzione ai **dati esistenti** in produzione: se la 5.x chiede migrazioni sulla tabella `activity_log`, provarle su una copia del database prima.
- [ ] Far passare i test dell'activity log e l'intera suite.

## 4. Tailwind 4 (con Vite 8 e `laravel-vite-plugin` 3)

Perché: restano **5 avvisi npm di gravità alta, tutti solo dev** (non finiscono nel codice servito; `npm audit --omit=dev` dà 0): `braces`, `chokidar`, `micromatch`, `fast-glob` e `tailwindcss` 3. `braces` ha un advisory su tutte le versioni pubblicate (la 3.0.3 è l'ultima): **non esiste una correzione**, l'unica via è lasciare Tailwind 3, che lo trascina. Il rischio reale è basso: si attiva solo con pattern glob controllati da un attaccante, qui sono i `content` di Tailwind scritti da noi.

Da fare:
- [ ] Migrare a Tailwind 4 (strumento ufficiale `npx @tailwindcss/upgrade`) su un branch dedicato.
- [ ] Aggiornare **Vite a 8 e `laravel-vite-plugin` a 3** insieme (il plugin 3 richiede Vite ^8). Verificare anche Node >= 22 in CI e in locale.
- [ ] Controllo visivo di tutte le schermate principali (non basta la build) e confronto del CSS prodotto.
- [ ] Alla fine **togliere l'override** `postcss-selector-parser` da `package.json` (non serve più) e controllare `npm audit`.
- [ ] Facoltativo, a parte: `@fullcalendar/*` 6 -> 7 (major).

## 5. Stile del codice: 185 file da formattare con Pint

Oggi `vendor/bin/pint --test` segnala **185 file** (già prima degli aggiornamenti: non dipendono da essi). Non c'è un `pint.json`, quindi vale il preset Laravel di default. Nota: il job della CI si chiama "PHPUnit + Pint" ma esegue **solo PHPUnit**, quindi nessuno se n'è accorto.

Regole più frequenti: `ordered_imports` (73 file), `not_operator_with_successor_space` (57), `concat_space` (56), `fully_qualified_strict_types` (55), `unary_operator_spaces` (43), `blank_line_before_statement` (26), `class_attributes_separation` (25), `no_unused_imports` (24).
Dove: `app/Http` 54 file, `tests/Feature` 43, `app/Services` 15, `app/Models` 14, `app/Console` 10, `app/Policies` 10, `database/migrations` 9, `database/seeders` 9, altri.

Perché non è stato applicato: è un diff enorme (185 file) che non cambia il comportamento ma genera conflitti con qualunque branch aperto (per esempio `feat/ui-base`).

Come farlo, in sicurezza:
1. [ ] Prima chiudere o unire i branch di lavoro aperti, così non ci sono conflitti.
2. [ ] Branch `style/pint` da `dev`: `vendor/bin/pint` (senza opzioni, riscrive i file).
3. [ ] `php artisan test` deve restare a **669 test verdi**. Se qualcosa cambia, non è solo stile: indagare.
4. [ ] **Un solo commit** che contiene solo la formattazione, senza altre modifiche.
5. [ ] Aggiungere lo SHA del commit a `.git-blame-ignore-revs` e lanciare `git config blame.ignoreRevsFile .git-blame-ignore-revs`, così `git blame` non punta a quel commit.
6. [ ] Aggiungere alla CI lo step `vendor/bin/pint --test` (come in `portfolio-chicca` e nel portfolio), in modo che resti pulito.
7. [ ] Facoltativo: creare un `pint.json` con le regole preferite prima di formattare (dopo è più difficile cambiare idea).

## 6. Automazione (facoltativo)

- [ ] Non esiste `.github/dependabot.yml`: creare una configurazione con `target-branch: dev` per npm, composer e GitHub Actions (frequenza settimanale), come nel portfolio e in `portfolio-chicca`. Ignorare i major di `laravel/framework` finché non si decide di migrare.
- [ ] Aggiungere alla CI `composer audit` e `npm audit --omit=dev` (oggi i controlli di sicurezza sono solo manuali).

## Come ricontrollare

```bash
composer audit && composer outdated --direct
npm audit && npm outdated
php artisan test
vendor/bin/pint --test
```
