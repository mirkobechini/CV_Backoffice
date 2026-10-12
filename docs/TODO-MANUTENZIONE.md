# Manutenzione: cosa resta da fare

Elenco aperto il 2026-10-11. **Aggiornamento 2026-10-12: tutti i punti sono stati chiusi** (issue #211-#218, tutti su `dev`). Lasciato come riferimento storico.

## Dove siamo

Tutto fatto, sul branch `dev` (CI verde su PHP 8.4 e 8.5):

- PHP minimo **8.4** (`composer.json` `^8.4`), CI su 8.4 e 8.5; produzione su 8.5.
- Composer aggiornato entro i vincoli (Livewire 4.4, Symfony 8.1, Guzzle, Pint...), **PHPUnit 13** e **Laravel Tinker 3**: 670 test verdi, nessuna deprecazione.
- **Laravel 13** (issue-212, PR #213): zero modifiche al codice applicativo necessarie.
- **spatie/laravel-activitylog 5** (issue-214, PR #215): trait/namespace aggiornati, migrazione dati reale su `activity_log` (`properties` -> `attribute_changes`) verificata su dati reali con round-trip migrate/rollback.
- **Tailwind 4 + Vite 8 + laravel-vite-plugin 3** (issue-216, PR #217): config spostata in `@theme`, `tailwind.config.js` rimosso, override `postcss-selector-parser` tolto. `npm audit` a 0 vulnerabilità.
- **Dependabot + audit in CI** (issue-218, PR #219): `.github/dependabot.yml` (composer/npm/github-actions, settimanale, target `dev`) e `composer audit`/`npm audit --omit=dev` nella pipeline.
- Test `ComposerLockPhpVersionTest`: fallisce se un `composer update` porta nel lock pacchetti che richiedono più PHP di quello dichiarato in `composer.json`.

## Facoltativi non fatti (fuori scope, a parte)

- `@fullcalendar/*` 6 -> 7 (major) — citato come "a parte" nel punto Tailwind, non toccato.
- `pint.json` con regole esplicite (oggi il preset Laravel di default è implicito).

## Prima del prossimo merge `dev` -> `main`

- [ ] **Controllo visivo manuale nel browser** delle schermate principali dopo la migrazione a Tailwind 4 (non bastano i test automatici/la build) — da fare prima di proporre il merge a `main`.

## Come ricontrollare

```bash
composer audit && composer outdated --direct
npm audit && npm outdated
php artisan test
vendor/bin/pint --test
```
