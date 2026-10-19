# Idee future — CV Backoffice

> Raccolta di feature proposte durante l'audit del 2026-10-04, non ancora implementate. Ordine di priorità deciso da Mirko il 2026-10-04 (vedi sezione "Ordine di lavoro concordato").

## Audit 2026-10-19 — tabella di marcia

> Audit completo (codice/architettura, sicurezza/privacy, accessibilità/responsive) + proposte feature. Stato da aggiornare via via che si procede. Legenda: ⬜ da fare · 🔄 in corso · ✅ fatto · ❌ scartato.

### Qualità del codice e architettura

| # | Area | Descrizione | Riferimento | Priorità | Stato |
|---|------|-------------|-------------|----------|-------|
| C1 | Bug | `complete()` applica un unico stato risolto/non-risolto a TUTTI i guasti/scadenze collegati, invece che per-item come `processCompletedItems()` | `app/Services/MaintenanceCompletionService.php:122-149` | Media | ✅ (issue-378) |
| C2 | Bug | `catch(\Throwable)` avvolge anche la validazione, inghiottendo `ValidationException` in un messaggio generico invece dei campi | `app/Http/Controllers/CsvImportController.php:22-46` | Media | ✅ |
| C3 | Bug/DRY | Calcolo giorni residui duplicato in due accessor | `app/Models/Deadline.php:267-293` | Bassa | ✅ |
| C4 | Errore silenziato | `catch(\Throwable)` senza alcun `Log::`, in violazione della regola AGENTS.md | `app/Http/Controllers/CsvImportController.php:44-46` | Media | ✅ |
| C5 | Performance | Dedup notifiche con `LIKE "%marker%"` non indicizzabile | `app/Console/Commands/GenerateNotifications.php:311-317` | Bassa | ✅ |
| C6 | Performance | Scadenze scadute filtrate in PHP invece che in SQL | `app/Http/Controllers/DashboardController.php:55-61` | Bassa | ✅ pre-filtro SQL sicuro (`Deadline::scopePossiblyExpired()`, sovrainsieme che non esclude mai un vero scaduto); il filtro PHP esatto su `automatic_status` resta invariato come unica fonte di verità |
| C7 | Performance | Veicoli ri-interrogati due volte per lo stesso conteggio | `app/Http/Controllers/DashboardController.php:144-146` | Bassa | ✅ |
| C8 | Performance | Manca indice su `is_renewed`, filtrato quasi ovunque nelle query scadenze | migrazione DB | Bassa | ✅ |
| C9 | Complessità | 3 livelli di closure annidate per esprimere la condizione orario officina | `app/Models/MaintenanceRecord.php:89-126` | Bassa | ❌ riverificato 2026-10-19: un'unica espressione SQL (via whereRaw) richiederebbe sintassi diversa per MySQL (prod) e SQLite (test), più fragile dei 3 livelli di closure attuali, già dentro la soglia dichiarata (max 3-4 livelli); i due scope restano complementi logici esatti, un helper condiviso richiederebbe parametri per direzione/null che lo renderebbero meno leggero |
| C10 | Duplicazione | Logica di rinnovo tagliando/cinghia reimplementata in due service diversi | `MaintenanceCompletionService.php` vs `DeadlineManualRenewalService.php` | Media | ✅ (estratto in `DeadlineRenewalCalculator`) |
| C11 | Design pattern | Strategy map per tipo scadenza invece di match/if ripetuti in 3 service | più file | Bassa | ✅ riverificato 2026-10-19: `DeadlineService::createNextDeadlineAfterRenewal()` unificato su `DeadlineRenewalCalculator` (issue-389) — divergeva davvero dagli altri due percorsi (niente `interval_km`/`last_mileage` sulla nuova scadenza tagliando), corretto unificando |
| C12a | File da spezzare | `MaintenanceCompletionService.php` (395→312 righe) | `app/Services/MaintenanceCompletionService.php` | Bassa | ✅ (estratto `TireDispositionService`, issue-380) |
| C12b | File da spezzare | `Deadline.php` (465→393 righe) | `app/Models/Deadline.php` | Bassa | ✅ (estratto `DeadlineStatusSyncService`, issue-382) |
| C12c | File da spezzare | `EquipmentController.php` (401→258 righe) | `app/Http/Controllers/Admin/EquipmentController.php` | Bassa | ✅ (estratti `EquipmentCollaudoController` + `EquipmentRevisionDateCalculator`, issue-383) |
| C12d | File da spezzare | Ricontrollati 2026-10-19: `VehicleController.php` (392), `DeadlineService.php` (360), `MaintenanceRecordController.php` (353), `DeadlineController.php` (334), `EquipmentMaintenanceRecordController.php` (326), `GroupController.php` (324), `GenerateNotifications.php` (320), `MileageLogController.php` (316), `Vehicle.php` (379) sono già sotto/vicino alla soglia ~400 di AGENTS.md — non frazionati per non introdurre astrazioni premature su file già accettabili | vedi dettaglio audit | Bassa | ❌ non necessario |
| C12e | File da spezzare | Viste oltre le 400 righe: `vehicles/edit.blade.php` (443→301), `dashboard.blade.php` (429→44), `vehicles/create.blade.php` (428→286) | vedi dettaglio audit | Bassa | ✅ (issue-385) — le altre viste elencate nell'audit iniziale (deadlines/edit, emails/report, ecc.) erano già sotto soglia a un controllo più attento |
| C13 | Test flaky | `VehicleScanTest::test_store_promotes_pending_scanned_photo_into_registration_card` fallisce in modo intermittente (~2/5 run) solo girando la suite completa, mai isolato né in combinazioni mirate — causa non individuata, non correlata a nessuna modifica di produzione di questo audit | `tests/Feature/VehicleScanTest.php:304` | Bassa | ⬜ |

### Sicurezza e privacy

| # | Area | Descrizione | Riferimento | Priorità | Stato |
|---|------|-------------|-------------|----------|-------|
| S1 | Config | `SESSION_SECURE_COOKIE` non impostata esplicitamente in produzione — verificare su Laravel Cloud | env produzione | Media | ⬜ |
| S2 | CSRF | `TelegramWebhookController` in `routes/web.php` prende il middleware CSRF di default — verificare se le notifiche falliscono silenziosamente (419) o se c'è un bypass non documentato | `routes/web.php:51` | Media | ⬜ |
| S3 | Hygiene | 3 advisory Composer ignorate senza un commento sul motivo | `composer.json:76-82` | Bassa | ✅ |
| S4 | Fragilità futura | `group_id` fillable su `Vehicle` ma nessuna request lo valida oggi — aggiungere un test che pinni l'invariante (nessuna richiesta futura deve poterlo scrivere da payload) | `app/Models/Vehicle.php` | Bassa | ✅ |

### Accessibilità

| # | Area | Descrizione | Riferimento | Priorità | Stato |
|---|------|-------------|-------------|----------|-------|
| A1 | ARIA | Bottoni icona-only (visualizza/modifica/elimina) con solo `title`, serve `aria-label` | varie card scheda veicolo | Media | ✅ |
| A2 | Contrasto | Badge ambra/verde in tema chiaro sotto soglia WCAG AA (4.5:1) | `resources/css/app.css:1146-1159,1093-1096` | Media | ✅ |
| A3 | Form | Messaggi di errore non collegati via `aria-describedby` | tutte le `@error` nei form create/edit | Media | 🔄 fatto per i componenti condivisi (date/month/time/tire-size-input); restano i form con `<input>` semplice (sweep più ampio, ~50 file) |
| A4 | Tastiera | Righe raggruppabili (tabelle scadenze/guasti) rispondono solo al click, non da tastiera | `resources/js/app.js:116-127` | Media | ✅ |
| A5 | Struttura | Manca `<h1>` su alcune pagine indice | pagine indice | Bassa | ✅ |
| A6 | Focus | Focus visibile mancante su `.search input` e `.role-select` | `resources/css/app.css:819-826,3306-3315` | Bassa | ✅ |
| A7 | ARIA | Pulsanti tema/notifiche senza `aria-label` (incoerente con l'hamburger che ce l'ha già) | `resources/views/layouts/app.blade.php:63-68` | Bassa | ✅ |

### Responsive / mobile

| # | Area | Descrizione | Riferimento | Priorità | Stato |
|---|------|-------------|-------------|----------|-------|
| R1 | Touch target | Pulsanti icona `.mini-btn` (26×26px) sotto la soglia comodamente toccabile (44px) — il problema pratico più sentito per i volontari sul campo | `resources/css/app.css:1188-1200` | Alta | ✅ (portati a 34×34px, incluso `.detail-btn`) |
| R2 | Layout | Repeater "misure pneumatici" si impila in modo disordinato su schermi stretti (<480px) | `resources/css/app.css:1941-1959` | Bassa | ✅ |

### Feature proposte

| # | Feature | Descrizione | Priorità | Stato |
|---|---------|-------------|----------|-------|
| F1 | Centro notifiche in-app | Pannello con lo storico notifiche generate da `app:generate-notifications`, non solo l'invio esterno Telegram | Da definire | ✅ già implementato (campanella con badge non lette in `layouts/app.blade.php`, `NotificationController`, `notifications/index.blade.php`) — nessuna feature mancante trovata rileggendo il codice |
| F2 | Check-in/check-out attrezzatura via QR | Estendere il QR già generato (idea #7) a uno scan-to-assign da telefono per equipaggiamento/gomme | Da definire | ⬜ |
| F3 | Autocompilazione dati veicolo | Approccio ibrido deciso il 2026-10-19: lo scan AI del libretto resta la fonte per i dati specifici del singolo veicolo (targa, VIN, data immatricolazione, colore — non ricavabili da un catalogo), ma per i dati di omologazione (carburante, cilindrata, potenza, massa — dove l'AI oggi sbaglia, es. carburante sempre "benzina") si incrocia il risultato con il catalogo di Mirko (openvehicle-data), cercando la variante per codice di omologazione letto dalla foto; l'AI resta fallback solo se non si trova un riscontro nel catalogo | Media | ⬜ |
| F4 | Log carburante/consumi | Tracciamento consumi nel tempo (oggi solo `fuel_type` statico, nessuno storico) | Da definire | ⬜ |
| F5 | Export conformità enti pubblici | PDF/CSV riassuntivo "stato flotta" per rendicontazione a Regione/Protezione Civile | Da definire | ⬜ |
| F6 | PWA installabile | Manifest + service worker per "aggiungi a home", in sinergia col redesign | Da definire | ✅ (issue-391) manifest.json + sw.js minimale (nessuna cache di pagine/dati, solo asset statici in /build/), icone generate, meta Apple per iOS |

**Scartata**: assegnazione turno/autista per appuntamento — non la facciamo.

**Nota**: il redesign complessivo resta pianificato per dopo; i punti di accessibilità/responsive (A1-A7, R1-R2) sono comunque più rapidi da sistemare ora che da portarsi dietro nel redesign.

---

## Ordine di lavoro concordato (2026-10-04) — TUTTE COMPLETATE (rilasciate in v1.5.0/v1.5.1)

1. ✅ Costo manutenzioni (campo opzionale, non obbligatorio)
2. ✅ Escalation scadenze non risolte
3. ✅ Verifica automatica del backup
4. ✅ 2FA per capo/sottocapo (opzionale per tutti, consigliato in dashboard per capo/sottocapo)
5. ✅ Bot Telegram per le notifiche (WhatsApp scartato, troppo costoso/complesso da configurare)
6. ✅ Manutenzione predittiva — rivista, vedi nota nella sezione 6 sotto
7. ✅ QR code su veicoli/attrezzature

## 8. Miglioramento gestione assicurazioni (2026-10-09) — ✅ COMPLETATA (issue-176)

Richiesta di Mirko, dopo audit del codice esistente (assicurazione modellata come tipo di `Deadline`, non un modello proprio). Da fare tutte e 5, poi compattare in un'unica revisione se possibile:

1. **Campi obbligatori**: oggi compagnia/numero polizza/premio/copertura/massimale/broker sono tutti `nullable` anche quando `type = Assicurazione` — si può salvare una polizza vuota. Rendere almeno i campi essenziali obbligatori quando il tipo è Assicurazione (`required_if`).
2. **"Tipo di copertura" da testo libero a select**: oggi è un campo di testo libero (placeholder "es. RCA, Kasko..."), impossibile raggruppare/filtrare in modo affidabile. Convertire in select con vocabolario fisso (RCA, Kasko, Furto e incendio, Cristalli, Assistenza stradale, Altro).
3. **Export CSV**: `CsvExportController::exportDeadlines()` oggi esporta solo Veicolo/Tipo/Data Scadenza/Stato/Rinnovata per tutti i tipi di scadenza — nessun campo assicurativo (compagnia, numero polizza, premio, massimale, broker) finisce nel CSV. Aggiungerli (almeno per le righe di tipo Assicurazione).
4. **Vista dedicata assicurazioni**: oggi si vedono solo filtrando l'elenco generico scadenze (`type_filter=Assicurazione`), con le colonne generiche tipo/data/stato. Una vista con colonne specifiche (compagnia, numero polizza, premio, scadenza, giorni rimanenti) sarebbe più utile a chi gestisce il budget.
5. **Rinnovo configurabile**: il flusso "rinnova senza appuntamento" (`DeadlineManualRenewalService`) aggiunge sempre 12 mesi fissi per le assicurazioni, non configurabile (polizze semestrali/pluriennali non gestibili lì). La creazione/modifica normale permette già di impostare qualsiasi data a mano.

**Deliberatamente escluso**: rimodellare l'assicurazione come modello proprio invece di un tipo di `Deadline` — refactor grosso, beneficio incerto, l'ADR giustifica bene il design attuale.

---

## 1. Costo manutenzioni

**Idea**: campo costo (opzionale) su `MaintenanceRecord` e `EquipmentMaintenanceRecord`, aggregato in un report per veicolo/anno.

**Perché**: utile per rendicontazione associazione, richiesto spesso da chi gestisce flotte reali. Oggi non c'è nessun tracciamento economico.

**Note**: campo esplicitamente NON obbligatorio (molte associazioni non hanno sempre il dato immediato, o l'intervento è gratuito/in convenzione).

## 2. Escalation scadenze non risolte

**Idea**: oggi c'è un solo avviso a finestra fissa (`deadlines.warning_months`, default 3 mesi prima della scadenza). Un'escalation se la scadenza supera la data senza intervento registrato (es. email ripetuta, badge di severità crescente, promozione a notifica più visibile) aumenterebbe l'efficacia senza nuova infrastruttura — il motore di notifiche esiste già (`app:generate-notifications`).

## 3. Verifica automatica del backup

**Idea**: esiste già un comando di backup (Artisan + bottone impostazioni). Manca un controllo periodico che il dump generato sia davvero ripristinabile (anche solo un test di integrità), per non scoprirlo corrotto nel momento peggiore.

## 4. 2FA per capo/sottocapo

**Idea**: secondo fattore di autenticazione sui ruoli con permessi ampi (capo/sottocapo), dato che l'app gestisce dati di un servizio pubblico. Laravel Fortify lo offre quasi pronto all'uso, stack già Sanctum-based.

## 5. Bot Telegram o WhatsApp per le notifiche

**Idea**: le associazioni di volontari coordinano più su gruppi Telegram/WhatsApp che via email. Un bot (webhook) che posta gli stessi eventi già generati da `app:generate-notifications` avrebbe adozione immediata — il motore di eventi esiste già, serve solo un nuovo "canale" di invio.

**Da decidere**: Telegram (bot API semplice, gratuita) vs WhatsApp (Business API, più complesso/costoso da configurare) — probabilmente Telegram è la scelta più pragmatica per partire.

## 6. Manutenzione predittiva — RIVISTA (2026-10-05)

**Idea originale**: statistica sul km/tempo medio tra guasti **per tipo di componente**, mostrata in dashboard, per anticipare un probabile guasto futuro.

**Problema trovato discutendone**: `Issue.description` è testo libero, non categorizzato — nessun modo affidabile di raggruppare "guasti motore" vs "guasti freni" senza un campo strutturato nuovo o un parsing NLP poco affidabile. Confermato essere esattamente la perplessità originale di Mirko.

**Versione implementata ora** (vedi issue/PR collegata): non una previsione per componente, ma un indicatore di tendenza affidabilità sulle riparazioni non programmate (`activity_type = 'Riparazione'`) di un singolo veicolo — intervallo medio storico vs intervallo più recente, badge "in peggioramento" solo con dati sufficienti. Solo sulla scheda veicolo.

**Nota per il futuro — categorizzazione per componente a basso sforzo**: se in futuro si vuole davvero l'analisi "per componente" dell'idea originale, il modo più economico per arrivarci senza perdere tempo ora né dover rielaborare lo storico: aggiungere un campo **categoria opzionale** su `Issue` (es. select: Motore, Freni, Elettrico, Carrozzeria, Pneumatici, Altro), nullable, compilato gradualmente da chi segnala un nuovo guasto — nessuna migrazione dei guasti già esistenti necessaria, un solo campo in più nel form. Quando ce ne sarà accumulato abbastanza, l'analisi per componente diventa possibile sui dati da quel momento in avanti.

## 7. QR code su veicoli/attrezzature

**Idea**: uno sticker con QR su ogni mezzo/attrezzatura che apre direttamente la sua scheda da smartphone (browser, non app nativa). Risolve il caso d'uso più comune di una "mobile app" (controllare stato o segnalare un guasto sul posto) con un decimo dello sforzo, zero sviluppo nativo.

---

## Altre idee emerse nell'audit, non in questo ordine di lavoro

- **API REST write + equipment**: oggi l'API è solo lettura (12 endpoint) e non copre equipment/equipment-issues/equipment-maintenance-records. Da estendere se mai un'app mobile nativa rientrasse in roadmap (oggi fuori scope).
- **Design pattern da introdurre quando si tocca di nuovo quel codice**: Strategy per le varianti cinghia (chain/dry/oil-bath) in `Deadline`/`DeadlineDueDateCalculator`; Pipeline per i passi di `MaintenanceCompletionService::complete()`; Eventi/Listener per i side-effect di completamento appuntamento invece di chiamate inline; Decorator/Strategy comune se si aggiungeranno altri formati di export oltre PDF/CSV.
