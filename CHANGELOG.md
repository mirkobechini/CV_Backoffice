# Changelog

Tutte le modifiche significative a questo progetto saranno documentate in questo file.

## [Unreleased]

### Fixed

- Import CSV: un errore di validazione finiva in un messaggio generico invece dei messaggi per campo; gli errori imprevisti ora vengono anche loggati.
- Scadenze: calcolo giorni residui deduplicato tra due accessor.
- Accessibilità: contrasto colori badge in tema chiaro portato sopra la soglia WCAG AA; `aria-label` sui pulsanti icona-only (visualizza/modifica/elimina/aggiungi); focus visibile su ricerca e select ruolo; righe di gruppo pieghevoli operabili da tastiera; `<h1>` nascosto su tutte le pagine (via breadcrumb); `aria-describedby` sui messaggi di errore dei componenti form condivisi.
- Responsive: pulsanti icona (`.mini-btn`/`.detail-btn`) ingranditi a 34×34px, target touch più comodo su telefono; campi misura pneumatico raggruppati su schermi stretti invece di andare a capo uno per uno.
- Notifiche: il testo mostrato all'utente non termina più con un marker tecnico illeggibile (es. "#deadline-42").
- Completamento intervento: un appuntamento con più guasti/scadenze collegati chiedeva un unico sì/no per tutti, impossibile risolverne uno sì e uno no. Ora una domanda per ciascuno.

### Changed

- Notifiche: deduplicazione via colonna indicizzata invece di una ricerca `LIKE` sul testo del messaggio.
- Dashboard: eliminata una query veicoli duplicata; aggiunto indice su `is_renewed` nelle scadenze.

- `composer.json`: motivate le advisory di sicurezza ignorate.
- Scadenze: calcolo di rinnovo (tagliando/cinghia/ministeriale/ossigeno) unificato in un solo punto, prima duplicato tra due servizi.
- Gomme: logica di collegamento/disposizione/montaggio estratta in un servizio dedicato; il montaggio (prima duplicato) è ora un solo punto.

## [v1.17.3] - 2026-10-19

### Fixed

- Scheda veicolo: le misure pneumatici consigliate sono ora elencate una per riga, invece che tutte su una riga sola separate da virgola.
- Form veicolo: il placeholder "Seleziona codice..." nelle select delle combinazioni ammesse resta visibile ma non è più selezionabile dal menu a tendina.
- Scheda veicolo: la scadenza assicurazione compariva sempre nella card "Scadenze", anche lontana nel tempo, in contraddizione con "Nessuna scadenza attiva" mostrato sopra. Ora segue lo stesso filtro delle altre scadenze (vicina/scaduta).
- Dashboard: un appuntamento di oggi con un orario impostato (es. 15:00) segnava il veicolo "in officina" già dalla mezzanotte, invece che da quell'ora. Senza orario il comportamento resta invariato (in officina da mezzanotte).

## [v1.17.2] - 2026-10-17

### Fixed

- Scheda veicolo: "misure consigliate" e "combinazioni ammesse" usavano per ogni riga due campi fratelli con nome `qualcosa[][sottocampo]` separati — non venivano accoppiati dal parser PHP di una submission reale, causando combinazioni mai salvate e codici misura (es. "A1") persi a favore del fallback numerico. Ora ogni riga usa un indice esplicito condiviso tra i suoi sottocampi.

### Changed

- Scheda veicolo: nelle "combinazioni ammesse", i campi di testo libero per codice anteriore/posteriore sono ora due select popolate con le sigle delle misure inserite sopra, aggiornate in tempo reale.

## [v1.17.1] - 2026-10-17

### Fixed

- Scheda veicolo: salvare un veicolo la cui misura gomme esistente non aveva un codice valorizzato cancellava silenziosamente quella misura, anche con un salvataggio che non toccava affatto la sezione misure. Ora riceve un codice di fallback invece di sparire.
- Scheda veicolo: aggiungere una combinazione ammessa gomme quando ne esisteva già una la sovrascriveva silenziosamente (indice del campo in collisione tra riga esistente e riga aggiunta via "Aggiungi una combinazione").

## [v1.17.0] - 2026-10-17

### Added

- Modifica multipla gomme (/admin/tires): la select "misura suggerita dal veicolo" ora compare anche lì quando le gomme selezionate appartengono tutte allo stesso veicolo (prima esisteva solo in creazione/modifica singola).
- "Prossimo cambio" gomme: per le gomme montate senza una data impostata a mano, la cui stagione è già quella attesa dal gruppo, viene ora stimato automaticamente dalle date di cambio stagionale (marcato come "stimato"); una gomma già fuori stagione non riceve una stima.
- Scheda veicolo: ogni misura pneumatici consigliata ha ora un codice (es. "A1", come sulla carta di circolazione) al posto della posizione fissa anteriore/posteriore/entrambi; nuova sezione "Combinazioni ammesse" per dire quali codici possono stare insieme davanti e dietro. L'avviso di misura non compatibile, quando si registra una gomma, ora segue queste combinazioni invece di una posizione decisa a priori.

### Changed

- Dashboard, card "Cambio gomme stagionale": un veicolo non ancora passato alla nuova stagione viene segnalato "da cambiare" già 30 giorni prima del cambio (prima restava "a posto" fino al giorno esatto); dopo la scadenza reale il badge diventa "scaduto".

### Fixed

- Log attività (`/admin/activity-log`): mostrava a chiunque lo aprisse le attività di TUTTI i gruppi, non solo del proprio (nessun filtro per gruppo sulla query). Ora filtrato per gruppo attivo.

## [v1.16.1] - 2026-10-15

### Changed

- Dashboard: "Veicoli in officina" affiancata a "Prossimi appuntamenti", "Cambio gomme stagionale" affiancata a "Lavaggi del mese" (prima occupavano da sole l'intera larghezza).

### Fixed

- Dashboard: le date di cambio gomme stagionale aggiornate dal capo non comparivano finché non scadeva naturalmente la cache dashboard (5 minuti) — Group non era tra i modelli osservati per l'invalidazione.
- Dashboard: un appuntamento di oggi non ancora rientrato compariva sia in "Veicoli in officina" sia in "Prossimi appuntamenti" (le due query si sovrapponevano su oggi). Ora compare solo nella prima.
- Statistiche "Km percorsi per veicolo" con filtro "Tutto": mostrava la distanza percorsa da quando il veicolo è tracciato nell'app (differenza tra prima e ultima lettura registrata), inferiore al chilometraggio reale per un mezzo che aveva già km sul contachilometri prima di adottare l'app. Ora mostra il km assoluto (ultima lettura), coerente con scheda veicolo e PDF.

## [v1.16.0] - 2026-10-15

### Added

- Form "Nuovo guasto": selezionando un veicolo, un pannello mostra i suoi guasti già aperti o in lavorazione, per ridurre il rischio di segnalazioni duplicate scritte con parole diverse (il controllo duplicati esistente intercetta solo un doppio submit identico entro 5 minuti).

- Pagina gruppo riorganizzata in tab "Panoramica"/"Impostazioni"; nuovo permesso per-gruppo che il capo può attivare per estendere la gestione di codice invito, inviti via email e creazione account ai sottocapi (di default riservata solo al capo).
- Dashboard: nuova card "Veicoli in officina" che elenca quali veicoli sono attualmente in officina, presso quale fornitore e da quanti giorni (prima il KPI mostrava solo un numero).

### Changed

- Date di cambio gomme stagionale: mostrate come giorno/mese invece del crudo "MM-DD", e visibili in sola lettura a capo/sottocapo soltanto (prima comparivano, senza formattazione, anche a un membro semplice).
- Card dashboard "Cambio gomme stagionale": oltre alla stagione corrente ora mostra esplicitamente la data del prossimo cambio, per evitare l'ambiguità di "gomme estive attese" letto come "sto aspettando le estive" quando il prossimo cambio reale è a invernali.

### Fixed

- Pannello superadmin: la casella per confermare il nome del gruppo nel dialog di eliminazione era invisibile (classe Bootstrap `form-control` mai caricata come CSS nell'app).

## [v1.15.2] - 2026-10-15

### Fixed

- Import CSV (guasti e chilometraggi): la risoluzione del veicolo per sigla poteva importare i dati sul veicolo sbagliato senza alcun avviso se la sigla corrispondeva a più veicoli. Ora segnala l'ambiguità, chiedendo di usare la targa (univoca) per disambiguare.
- Statistiche "Guasti per parte e veicolo": due veicoli con la stessa sigla sommavano i guasti sotto un'unica riga invece di restare distinti (stesso bug già corretto in v1.15.1 per le altre statistiche per veicolo).

## [v1.15.1] - 2026-10-15

### Fixed

- Statistiche per veicolo (km percorsi, guasti per veicolo, costo per veicolo, costo per km): `internal_code` non ha vincolo di unicità, quindi due veicoli diversi con lo stesso codice (es. un mezzo dismesso sostituito con lo stesso numero di flotta) potevano far sovrascrivere silenziosamente i dati di uno con quelli dell'altro.

## [v1.15.0] - 2026-10-15

### Added

- Pannello superadmin: modifica del nome di un'associazione, correzione di nome/email del capo (con reinvio del link per impostare la password), form di rinnovo licenza precompilato con i valori della licenza attuale.
- Statistiche: nuove tab "Costi" (spesa per anno/veicolo/officina, costo per km percorso) e "Affidabilità e utilizzo" (km percorsi, classifica guasti per veicolo, tempo medio di fermo officina); pagina riorganizzata a tab invece di sezioni verticali.
- Sidebar: nuova sezione "Report" con Stato documenti e Statistiche.
- Modale di completamento del form di modifica appuntamento: chiede ora la disposizione (magazzino/dismessa) delle gomme sostituite, come il bottone "Completa" dedicato.

### Fixed

- Salvare un appuntamento "Cambio Gomme" con data di rientro dal form di modifica normale (non il bottone "Completa") non montava più le gomme collegate.
- Verifica email: `User` non implementava il contratto `MustVerifyEmail` (mai attivato dallo scaffold Breeze), quindi il middleware `verified` già presente su più rotte non ha mai avuto effetto. Ora attivo (anche prima dell'attivazione del 2FA), con migrazione di backfill per non bloccare gli account esistenti.

## [v1.14.0] - 2026-10-15

### Changed

- Creazione di un nuovo utente (sia il primo capo da `/superadmin`, sia un sottocapo/membro da `/admin` dentro un gruppo esistente): chi crea l'account non ne sceglie più la password. Il nuovo utente riceve un'email con un link per impostarla da solo.
- Email di reset/impostazione password localizzata in italiano (era in inglese di default).

## [v1.13.0] - 2026-10-15

### Added

- Pannello superadmin: bottone per creare una nuova associazione (gruppo + primo capo, oppure se stesso come capo) direttamente da `/superadmin`; link di accesso al pannello dalla sidebar di `/admin`.
- Eliminazione di un'associazione ora disponibile da `/superadmin` (stessa conferma a scrittura del nome).

### Changed

- Creazione ed eliminazione di un gruppo sono ora esclusive del pannello superadmin: rimosse da `/admin`, dove qualsiasi utente poteva creare/eliminare associazioni bypassando il sistema di licenze. `/admin/groups` resta per la gestione (rinomina, inviti, ruoli, join via codice) di gruppi già esistenti.

## [v1.12.0] - 2026-10-15

### Added

- Pannello **superadmin** (`/superadmin`, namespace separato da `/admin`, nuovo flag `is_superadmin` impostabile solo via `php artisan make:superadmin {email}`): gestione licenze per associazione (tier Free/Pro/Enterprise, limiti veicoli/utenti, feature incluse) e codici sconto.
- Licenze: enforcement dei limiti di veicoli/utenti per gruppo (nessun limite se non assegnata una licenza), feature gating di Statistiche e revisione AI parti guasto (incluse da Pro in su), banner nel pannello quando la licenza del gruppo è scaduta.
- Backup database: comando di ripristino (`php artisan app:restore-database`, CLI-only, richiede conferma) e pulizia automatica dei backup più vecchi di 30 giorni (`app:prune-backups`, schedulato).

### Changed

- Gruppi: ora recuperabili (soft delete) invece di cancellazione definitiva; eliminarne uno richiede di ridigitare il nome del gruppo per confermare.

## [v1.11.0] - 2026-10-14

### Added

- Nuova pagina **Statistiche** (`/admin/statistics`, solo capi/sottocapi): guasti per parte del veicolo (totale flotta e per veicolo), andamento mensile, costo riparazioni per parte (diviso in parti uguali quando un appuntamento copre più guasti insieme).

## [v1.10.0] - 2026-10-14

### Added

- Show veicolo: nuove card "Storico interventi" (tutti gli appuntamenti) e "Storico cambi gomme" (montaggi/smontaggi) — i dati erano già disponibili ma non mostrati da nessuna vista.
- PDF scheda veicolo aggiornato: dettaglio coperture assicurative, orario appuntamento, colonna "parte" sui guasti, sezione storico cambi gomme.
- Scadenze assicurative: data di scadenza con giorno esatto (es. "15/06/2026"), non più solo mese/anno — le altre tipologie (revisioni, tagliando, cinghia) restano invariate.

### Changed

- Officine (select appuntamenti veicolo/attrezzatura) e parti guasto (select/pagina di revisione) ora ordinate alfabeticamente.

## [v1.9.2] - 2026-10-14

### Added

- Vocabolario parti guasto: aggiunte "Alimentazione" (AdBlue, metano/GPL) e "Interni/Abitacolo" (sedili, parasole, supporti/porta telecomando) — casi reali che prima finivano forzatamente in "Allestimento" o "Altro".

## [v1.9.1] - 2026-10-14

### Fixed

- Revisione AI dei guasti: "Genera suggerimenti" classificava tutti i guasti in un'unica richiesta sincrona, superando il timeout del proxy Cloudflare davanti a Laravel Cloud quando erano molti. Ora via AJAX a blocchi da 25.

### Changed

- Vocabolario parti guasto: tolto "Impianto sanitario" (si sovrapponeva alle attrezzature già tracciate a parte — barella, ossigeno, DAE...); aggiunte voci più specifiche per il veicolo (Sterzo, Impianto elettrico, Scarico, Vetri, Allestimento).

## [v1.9.0] - 2026-10-14

### Added

- Guasti: nuovo campo obbligatorio "parte del veicolo coinvolta" (vocabolario fisso: motore, freni, sospensioni, pneumatici, elettronica, carrozzeria, climatizzazione, trasmissione, impianto sanitario, illuminazione/sirena, altro), per abilitare statistiche sui guasti per parte in futuro. Indice guasti filtrabile/raggruppabile anche per parte. Nuova pagina di revisione (`/admin/issues/backfill-parts`, solo capi/sottocapi) per allineare i guasti esistenti: suggerimento AI per ciascuno (OpenRouter, stessa infrastruttura dello scan libretto), sempre da confermare o correggere prima di salvare.

- Orario facoltativo sulla data appuntamento (MaintenanceRecord): mostrato quando compilato (es. "12/10/2026 14:30"), altrimenti solo la data come prima.
- Dashboard: nuova card "Lavaggi del mese" — quanti veicoli sono stati lavati nel mese corrente (appuntamento "Lavaggio" concluso), con dettaglio espandibile su quali e quando, e quali mancano ancora.

## [v1.8.0] - 2026-10-14

### Added

- Misure pneumatici consigliate del veicolo: ogni misura ora ha anche una posizione (anteriore/posteriore/entrambi), perché il libretto a volte indica misure diverse per assale che non vanno bene a vicenda. L'avviso di misura non corrispondente (`Tire::sizeMismatchWarning`) ora tiene conto della posizione della gomma. La scansione automatica del libretto prova a leggere anche la posizione (default "entrambi" se non specificata).

### Changed

- Index attrezzature: rimossa la colonna "Prossimo collaudo" (vuota per tutto tranne gli estintori); quando il collaudo è vicino o scaduto, l'avviso compare ora nella colonna "Data di revisione".

### Fixed

- Creazione pneumatico: il form permetteva una sola posizione per volta nonostante il messaggio dicesse "Set di gomme creato con successo" — per montare un set completo servivano 4 invii separati. Aggiunto il selettore "Quante gomme" (set completo/coppia/singola), già presente nel flusso "Cambio Gomme".

## [v1.7.1] - 2026-10-13

### Added

- Il report riassuntivo (PDF) arriva anche via Telegram a chi ha collegato l'account, non solo via email — canale indipendente: se la mail non è configurata o fallisce, il report arriva comunque su Telegram.
- Corretta dipendenza mancante `resend/resend-php` (stesso tipo di bug di `league/flysystem-aws-s3-v3`) e documentato il setup email in produzione via Resend.

### Fixed

- `league/flysystem-aws-s3-v3` non era una dipendenza diretta: `Storage::disk('s3')` crashava in produzione, probabile causa di upload rotti (carta di circolazione, foto guasti) oltre all'errore osservato sulla verifica dei backup.

## [v1.7.0] - 2026-10-12

### Changed

- Formattazione del codice uniformata con Pint (preset Laravel di default); aggiunto `pint --test` alla CI.
- `laravel/framework` 12 → **13**, con l'aggiornamento a cascata di Guzzle, Symfony e `brick/math`. Nessuna modifica al codice applicativo necessaria.
- `spatie/laravel-activitylog` 4 → **5**: trait unificato `HasActivity`, namespace di `LogOptions` cambiato, config semplificata. Migrazione dati automatica dei log di attività esistenti (i valori prima/dopo passano dalla colonna `properties` alla nuova `attribute_changes`) — verificata su dati reali, non solo in teoria.
- Tailwind CSS 3 → **4**: configurazione spostata da `tailwind.config.js` (rimosso) al blocco `@theme` in CSS; `vite` 6 → 8 e `laravel-vite-plugin` 1 → 3 a corredo. Chiude la catena di dipendenze vulnerabile (`braces`/`chokidar`/`micromatch`/`fast-glob`) citata in v1.6.0: rimosso l'override `postcss-selector-parser`, non più necessario (0 vulnerabilità npm).
- Aggiunto `.github/dependabot.yml` (composer/npm/github-actions, settimanale, verso `dev`, ignora i major di `laravel/framework`) e `composer audit`/`npm audit --omit=dev` in CI: i controlli di sicurezza sulle dipendenze non sono più solo manuali.

## [v1.6.0] - 2026-10-11

### Added

- Gestione assicurazioni migliorata: campi obbligatori per compagnia/numero polizza/coperture quando il tipo è "Assicurazione", poi estesa a **più coperture insieme** (es. RCA + Kasko), ciascuna con il proprio costo, invece del precedente tipo+premio singolo. Vocabolario coperture ampliato (Furto e Incendio separati, più Infortuni conducente/Tutela legale/Eventi speciali/Globale complementari). Export CSV con colonne dedicate, vista dedicata "Assicurazioni" con il totale premi, durata di rinnovo configurabile per il flusso "rinnova senza appuntamento".
- Show di una scadenza: le coperture assicurative sono mostrate in un layout tipo/costo leggibile, e una nuova card "Storico rinnovi" percorre l'intera catena di rinnovi (non solo il passo immediato).
- Nuova pagina **"Stato documenti"** (`/admin/documents`): una riga per veicolo con lo stato di carta di circolazione, garanzia e ogni tipologia di scadenza; le attrezzature in una sezione separata, con filtro per stato e raggruppamento per tipo/veicolo.
- Dashboard: nuova card con il premio assicurativo annuo totale delle polizze attive.
- Test `ComposerLockPhpVersionTest`: fallisce se il `composer.lock` richiede una versione di PHP più recente di quella dichiarata in `composer.json`.

### Fixed

- La durata di rinnovo configurabile delle polizze (`insurance_renewal_months`) veniva validata ma non salvata dal form di creazione/modifica scadenza.

### Security

- Un utente privo di gruppo attivo (stato raggiungibile rimuovendo un membro dal suo unico gruppo) otteneva accesso illimitato ai dati di **tutti** i gruppi, sia nelle liste che nell'accesso diretto per id — corretto a "nessun accesso" invece di "nessuna restrizione"; bloccata anche la rimozione di un membro dal suo unico gruppo.
- Il webhook del bot Telegram accettava richieste non autenticate quando il secret non era configurato (fail-open); ora rifiuta sempre in quel caso (fail-closed), con confronto a tempo costante.
- Backup del database e documenti caricati (carta di circolazione, foto guasti): fallback silenzioso al disco pubblico se `UPLOADS_DISK` non era configurato in produzione, corretto con un controllo di avvio che segnala il problema; i link ai file ora sono firmati e temporanei (non più permanenti), così il bucket di storage può restare privato.
- Whitelist del nome colonna in `Searchable::scopeSearch()` prima di usarlo in una query raw (difesa in profondità, non derivato da input utente oggi).
- Seeder di sviluppo con credenziali note bloccato fuori da ambiente locale/testing.

### Changed

- PHP minimo richiesto: **8.4** (`composer.json` `^8.4`); in produzione consigliata la 8.5 (PHP 8.2 termina il supporto di sicurezza il 31/12/2026). La CI ora testa 8.4 e 8.5. **Prima del deploy, impostare PHP 8.4/8.5 nell'ambiente di Laravel Cloud.**
- Dipendenze PHP aggiornate entro i vincoli (Livewire 4.4, Symfony 8.1, Guzzle, Pint, PHPUnit e altre minori/patch).
- Dipendenze di sviluppo npm: `concurrently` 10, `npm audit fix` e aggiornamenti minori (Sass, Vite 6.4.4, Font Awesome...); override `postcss-selector-parser` ^7.1.6 per correggere l'avviso di complessità quadratica (output CSS verificato identico al byte). Da 10 a 5 avvisi, tutti dev-only: restano `braces` (nessuna versione corretta pubblicata, la 3.0.3 è l'ultima) e i pacchetti che lo usano nella catena di `tailwindcss` 3; si chiude solo con la migrazione a Tailwind 4, da pianificare a parte.
- Strumenti di sviluppo PHP: PHPUnit 13 (possibile con PHP ^8.4) e Laravel Tinker 3.

## [v1.5.1] - 2026-10-05

### Fixed

- La schermata di collegamento Telegram mostrava letteralmente il testo `@{{ $telegramBotUsername }}` invece del nome reale del bot, per un errore di sintassi Blade (`@{{ ... }}` è l'escape che mostra il testo senza valutarlo, non "chiocciola letterale + variabile").
- La card "Guasti aperti" in dashboard non aveva alcun limite di altezza: con molti guasti la pagina diventava eccessivamente lunga. Ora scorre internamente.
- Lo scrollbar della card "Guasti" nella scheda veicolo esisteva già ma era praticamente invisibile (colore troppo simile allo sfondo).

### Changed

- I codici di recupero del 2FA (mostrati una sola volta) ora hanno anche i bottoni "Copia negli appunti" e "Scarica file", non solo il testo semplice.

---

## [v1.5.0] - 2026-10-05

### Added

- Campo costo opzionale (non obbligatorio) sugli appuntamenti, sia veicolo che attrezzatura: visibile in create/edit/show/index, incluso nell'export PDF scheda veicolo e nel CSV appuntamenti.
- Escalation per le scadenze scadute e non rinnovate: oltre all'avviso "in arrivo" già esistente, ora un'email/notifica ripetuta ogni 7 giorni finché il problema non viene risolto, e una badge più evidente ("in peggioramento") in dashboard dopo 7+ giorni di ritardo.
- Backup del database ora anche automatico (giornaliero, oltre al bottone manuale esistente), con una verifica automatica successiva che controlla che l'ultimo backup sia recente, in formato valido e non troncato — avvisa capo/sottocapo in caso di problema.
- Autenticazione a due fattori (TOTP) opzionale per qualsiasi utente, dalle impostazioni del profilo: QR/chiave manuale, codici di recupero monouso, disattivazione protetta da password. Un banner in dashboard la consiglia a capo/sottocapo che non l'hanno ancora attivata.
- Notifiche anche via bot Telegram, in aggiunta all'email esistente: ogni utente può collegare il proprio account Telegram dalle impostazioni notifiche e riceve lì gli stessi eventi.
- Etichetta QR stampabile per ogni veicolo e attrezzatura (bottone "QR" nella relativa scheda): un PDF pronto da stampare e attaccare sul mezzo/sull'attrezzatura, che apre la scheda direttamente da smartphone.
- Indicatore di "tendenza riparazioni" sulla scheda veicolo: per le riparazioni non programmate, confronta l'intervallo più recente con la media storica del veicolo e segnala se sta peggiorando. Non è una previsione di quando avverrà il prossimo guasto (impossibile per definizione per un guasto non programmato), solo un segnale di attenzione basato sui dati di quel veicolo.

### Fixed

- **Bug di produzione**: il comando/bottone di backup del database non ha mai funzionato in produzione (MySQL) — usava una query specifica di SQLite, mai testata contro MySQL. Ora funziona su entrambi i motori ed è stato spostato sul disco persistente (R2) invece che su quello locale non persistente.
- **Bug di produzione**: il report email settimanale/mensile andava in errore ("Call to a member function format() on null") quando tra le scadenze scadute ce n'era una "a km" (es. cinghia a secco), che per definizione non ha una data di scadenza — mancava da sempre il controllo già presente altrove (dashboard, scheda veicolo) su questo stesso campo.
- `ANALISI_PROGETTO.md` (documento di analisi superato, datato e non più aggiornato) e un file di output di test dimenticato nel repository sono stati rimossi.
- 9 vulnerabilità di sicurezza segnalate su `axios` (dipendenza frontend) risolte con l'aggiornamento alla versione patchata.
- 5 blocchi `catch` che mascheravano silenziosamente un errore (import CSV, log attività) ora loggano l'eccezione reale invece di fallire in silenzio.

### Changed

- Frazionati tutti gli 8 file del progetto che superavano le 400 righe (il limite dichiarato dalle linee guida interne), nessun cambio di comportamento: `MaintenanceRecordController` (634→337), le view create/edit degli appuntamenti veicolo (598/618→142/166), `vehicles/show.blade.php` (616→118), l'export PDF scheda veicolo (542→22), il model `Deadline` (527→389), `DeadlineService` (489→337), `VehicleController` (457→360) — ciascuno frazionato in servizi/componenti/partial dedicati, con copertura test invariata o estesa.

---

## [v1.4.0] - 2026-10-03

### Added

- Nuova sezione "Guasti e Appuntamenti Attrezzature", separata da quella dei veicoli: le attrezzature (estintori, barelle, DAE, ecc.) possono ora avere guasti segnalati e appuntamenti con un fornitore come i veicoli, con la differenza che un appuntamento attrezzature può coinvolgere più attrezzature insieme (es. collaudo di più estintori dallo stesso fornitore nello stesso giorno). Le scadenze di revisione/collaudo restano quelle già esistenti, invariate.
- Nell'elenco fornitori, filtro per "usate per veicoli" / "usate per attrezzature" / tutte, basato sull'uso reale (non una categoria fissa: lo stesso fornitore può servire entrambi).
- Nei form gomme e cambio gomme, un select "misura suggerita dal veicolo" popolato dalle misure consigliate del veicolo selezionato, per non doverle ritrascrivere a mano.
- Al completamento di un cambio gomme con più gomme sostituite nello stesso appuntamento, la disposizione (magazzino/dismessa) si sceglie ora indipendentemente per ciascuna, non più un'unica scelta per tutte.
- Elenco attrezzature: raggruppamento e ordinamento per tipo, stato e veicolo, come già per veicoli/appuntamenti/gomme.
- Comando diagnostico `deadlines:inspect {veicolo}` (sola lettura): mostra tutte le scadenze di un veicolo, comprese quelle eliminate, con il relativo registro attività.
- Nei form veicolo, un hint col codice del libretto corrispondente a ciascun campo tecnico (es. "Numero di telaio (VIN) (E.)"), per trovare più facilmente il dato sul documento.

### Fixed

- **Bug di produzione**: una scadenza cinghia a secco già rinnovata poteva nascondere quella attiva nata dal rinnovo, sia nella scheda veicolo che nell'elenco scadenze — entrambe avevano `due_date` nullo (le cinghie a secco scadono solo a km) e l'ordinamento non riusciva a distinguerle. Nessun dato era stato perso, solo nascosto da un bug di visualizzazione.
- Completare un appuntamento "Cambio Cinghia" quando il veicolo non aveva ancora una scadenza cinghia da rinnovare non faceva assolutamente nulla, in silenzio: il cambio veniva registrato ma nessuna scadenza nasceva mai. Ora la crea.
- Modificare un appuntamento senza cambiare le date poteva essere bloccato da un conflitto di sovrapposizione preesistente con un altro appuntamento, anche quando la modifica non toccava affatto le date.
- Negli hint di due campi del form attrezzature, gli apostrofi venivano mostrati letteralmente come "&#039;" invece che come apostrofo, per una doppia escape HTML.
- La scansione AI del libretto richiedeva sempre fronte e retro, ma nessun dato veniva letto dal retro: ora basta il fronte.
- Isolamento per gruppo mancante sugli appuntamenti attrezzature: un capo poteva vedere/modificare/eliminare per id diretto un appuntamento di un gruppo diverso quando coinvolgeva una sola attrezzatura (o più attrezzature dello stesso veicolo) — le liste restavano filtrate correttamente, solo l'accesso diretto non lo era.

### Changed

- `MaintenanceRecordController` (il file più grande del progetto, 911 righe) ridotto a 627: la logica di completamento appuntamento (chiusura guasti, rinnovo scadenze, montaggio gomme) è stata estratta in `MaintenanceCompletionService`.
- `CsvImportController` (678 righe) ridotto a 140: la logica di validazione/importazione per guasti e chilometraggi è stata estratta in classi dedicate (pattern Strategy: `IssueCsvImporter`, `MileageLogCsvImporter`), più facili da testare ed estendere a nuovi tipi di entità in futuro.

## [v1.3.0] - 2026-10-01

### Added

- Un guasto può ora coprire più pneumatici insieme (es. tutte e quattro le gomme forate nello stesso evento), invece di dover creare un guasto separato per ogni gomma.
- La cinghia di distribuzione distingue ora catena (nessuna scadenza), cinghia a secco (scadenza solo a km) e cinghia a bagno d'olio (scadenza a km o a 10 anni) — prima un unico flag "cinghia sì/no" generava sempre la stessa scadenza combinata km+tempo per ogni veicolo con cinghia.
- Più pneumatici possono essere modificati insieme (marca/modello/misura) dall'elenco gomme, utile per gomme dello stesso lotto.
- Più attrezzature possono essere revisionate/collaudate insieme con un'unica data condivisa, per il caso comune di controlli fatti in blocco (es. tutti gli estintori lo stesso giorno).
- Nuovo PDF riepilogo flotta dall'elenco veicoli: una riga per veicolo con prossimo tagliando, revisioni e ultimo chilometraggio.
- Gli appuntamenti ora validano il chilometraggio inserito contro la cronologia del veicolo (stessa regola già in uso per l'inserimento manuale dei km), con l'ultimo km noto mostrato come suggerimento nel campo.

### Fixed

- L'indice attrezzature mostrava la data dell'ultima revisione accanto al conto alla rovescia della prossima scadenza — due date diverse presentate come se corrispondessero. Mostra ora la data di scadenza corretta, e include anche la scadenza del collaudo (prima ignorata silenziosamente se diversa da quella della revisione).
- Un errore di formato mese/anno su anno bisestile poteva far calcolare la scadenza di un'attrezzatura un mese più avanti del dovuto.
- Completare un appuntamento di tipo "Cambio Cinghia" andava sempre in errore (argomento mancante): non aveva mai funzionato.
- Il report email periodico non veniva inviato da settimane: causa reale sullo scheduler di Laravel Cloud (non configurato), ma nel frattempo i comandi schedulati ora loggano gli errori invece di fallire in silenzio, e un destinatario che fallisce non blocca più l'invio agli altri.
- Le misure pneumatico potevano salvarsi con lettere minuscole, rendendo due gomme identiche difficili da confrontare a colpo d'occhio; ora normalizzate in maiuscolo.
- Chiarite le etichette dei campi data nel form attrezzature (distinzione tra "ultima effettuata" e "prossima", con testo che spiega il calcolo automatico).

### Docs

- README e ADR allineati con l'architettura reale: ruoli corretti (capo/sottocapo/member), aggiunte le sezioni mancanti su multi-tenancy a gruppi e scansione AI del libretto, corretta la nota sullo scheduler.

## [v1.2.54] - 2026-09-24

### Fixed

- La carta di circolazione (e le foto guasti) continuavano a dare "pagina non trovata" anche dopo `storage:link`: Laravel Cloud gira su compute che non serve i file scritti sul disco locale dell'app, quindi il disco "public" non funziona in produzione. I caricamenti ora passano da un disco configurabile (`UPLOADS_DISK`, vedi anche `config/filesystems.php`), locale in sviluppo e Cloudflare R2 (compatibile S3) in produzione — richiede impostare le variabili `UPLOADS_DISK=s3` e `R2_*` nel pannello Laravel Cloud (vedi docs/DEPLOY.md).

### Added

- Le nuove specifiche veicolo (VIN, colore, posti, categoria, classe ambientale, massa, cilindrata, potenza, misure pneumatici) sono ora incluse anche nella scheda veicolo PDF, non solo nella pagina web.

## [v1.2.53] - 2026-09-24

### Added

- Un veicolo può avere più di una misura pneumatici consigliata (es. assale anteriore diverso dal posteriore): il campo è ora un elenco ripetibile invece di un valore singolo.
- Scheda veicolo: nuova card "Specifiche tecniche" che mostra VIN, colore, posti, categoria, classe ambientale, massa massima, cilindrata, potenza e misure pneumatici — questi dati erano raccoglibili da creazione/modifica ma non venivano mostrati da nessuna parte.
- La scansione del libretto è ora disponibile anche dalla pagina di modifica di un veicolo esistente, non solo in creazione.

### Fixed

- Aprire la carta di circolazione caricata restituiva "pagina non trovata": mancava il collegamento simbolico `public/storage` (creato con `php artisan storage:link`, ora anche nei comandi post-deploy in docs/DEPLOY.md).
- Guasto collegato a un pneumatico: l'elenco pneumatici mostrava quelli di tutti i veicoli del gruppo invece di solo quelli del veicolo selezionato, e non indicava la posizione (anteriore/posteriore, sinistra/destra) per distinguerli.

## [v1.2.52] - 2026-09-24

### Changed

- Scansione libretto: ora richiede sia il fronte sia il retro (i timbri di revisione periodica sono sul retro), invece di una sola foto. Il pulsante "Compila automaticamente con AI" si abilita solo dopo aver caricato entrambe le facciate; solo il fronte viene conservato come carta di circolazione del veicolo.

## [v1.2.51] - 2026-09-24

### Changed

- Creazione veicolo: un solo campo di caricamento file per "Carta di circolazione", invece di due selettori distinti (uno per la scansione, uno per l'allegato). Il pulsante "Compila automaticamente con AI" ora scansiona il file già selezionato lì, invece di richiederne uno separato, ed è coerente nello stile con il resto del form.

## [v1.2.50] - 2026-09-24

### Changed

- Creazione veicolo: dopo una scansione del libretto, il campo "Carta di circolazione" ora indica che la foto scansionata è già allegata (invece di apparire vuoto), chiarendo che caricare un file lì la sostituisce anziché essere un passaggio obbligatorio.

## [v1.2.49] - 2026-09-24

### Added

- Scansione del libretto di circolazione alla creazione di un veicolo: carica una foto, un LLM vision (OpenRouter di default, provider/modello configurabili via `.env`) estrae targa, marca/modello (abbinati automaticamente a quelli esistenti), carburante, data di immatricolazione, VIN, colore, posti, classe ambientale, massa massima, cilindrata, potenza, categoria veicolo e misura pneumatici consigliata, precompilando il form di creazione per la verifica dell'utente prima del salvataggio. La foto caricata diventa la carta di circolazione del veicolo. Il campo "cinghia/catena di distribuzione" riceve anch'esso un suggerimento dall'AI, segnalato come tale perché è una stima e non un dato letto dal documento.
- Nuovi campi facoltativi sul veicolo: numero di telaio, colore, numero posti, classe ambientale, massa massima ammissibile, cilindrata, potenza, categoria veicolo, misura pneumatici consigliata.
- Misura pneumatici (sul veicolo e sulle gomme) ora inserita tramite un input strutturato (larghezza/profilo/cerchio/rinforzato/indice) che garantisce un formato standard, invece di testo libero.
- Avviso (non bloccante) quando la misura di una gomma montata/creata non corrisponde alla misura consigliata per il veicolo.

## [v1.2.48] - 2026-09-24

### Added

- Scadenza "Assicurazione": nuovi campi dedicati (compagnia, numero polizza, premio annuo, tipo di copertura, massimale, contatto broker/agenzia, note), mostrati solo per questo tipo nella creazione/modifica e in una card dedicata sulla scheda scadenza.
- "Rinnova adesso" ora disponibile anche per l'Assicurazione: registra il rinnovo della polizza con intervallo fisso di un anno, riportando sulla prossima scadenza i dati della polizza già inseriti (modificabili in seguito).

## [v1.2.47] - 2026-09-24

### Added

- "Rinnova adesso" sulla scheda scadenza: per un veicolo acquistato usato la cui ultima revisione/tagliando/cinghia è già stata fatta dal precedente proprietario, permette di registrare la data (e opzionalmente il km) di quel rinnovo senza dover creare un appuntamento in officina. Calcola la prossima scadenza con la stessa logica del completamento di un intervento. Disponibile per Revisione Ministeriale, Revisione Impianto Ossigeno, Tagliando e Cinghia Distribuzione.
## [v1.2.46] - 2026-09-24

### Changed

- Distribuzione veicolo: da checkbox "cinghia sì/no" a scelta esplicita "Cinghia di distribuzione" / "Catena di distribuzione". Il comportamento resta lo stesso (cinghia genera la scadenza periodica, catena no), cambia solo la chiarezza del form e della scheda veicolo.
## [v1.2.45] - 2026-09-24

### Changed

- Import CSV chilometraggi (formato pivot): il controllo duplicati eseguiva una query per ogni cella veicolo×mese del file (fino a centinaia per un import tipico); ora è un'unica query per l'intero import.
## [v1.2.44] - 2026-09-24

### Security

- Rimosso SVG dai formati accettati per la foto di un guasto: se il file conteneva `<script>`, aprire direttamente l'URL salvato (non tramite `<img>`) lo eseguiva nel browser.
## [v1.2.43] - 2026-09-24

### Changed

- Elenco veicoli: rimossa una query full-collection duplicata (calcolava due volte la stessa cosa per la stat "flotta completa" e per il filtro "da integrare") e sostituito un conteggio guasti caricato in memoria e sommato in PHP con una singola query di conteggio diretta.
- Elenco chilometraggi: il calcolo del delta km leggeva i mileage log di TUTTI i gruppi invece di solo quelli del proprio.
## [v1.2.42] - 2026-09-24

### Security

- Gli export CSV non neutralizzavano l'iniezione di formule: un campo testo libero (descrizione guasto, nome fornitore, ecc.) che iniziasse con `=`, `+`, `-` o `@` veniva interpretato come formula da Excel/Sheets/LibreOffice alla riapertura del file esportato. Ora questi valori vengono anteposti da un apice per forzarne l'interpretazione come testo letterale.
## [v1.2.41] - 2026-09-24

### Security

- L'import CSV (guasti/chilometraggi) verificava il veicolo solo nel passo di anteprima; la conferma finale riaccettava l'id veicolo così com'era tornato dal form, senza ricontrollare che appartenesse ancora al proprio gruppo — un campo nascosto alterabile prima dell'invio.
## [v1.2.40] - 2026-09-24

### Fixed

- Il report email giornaliero/settimanale/mensile mostrava "Tra -N giorni" per ogni scadenza imminente invece di "Tra N giorni" (stesso bug del `diffInDays()` invertito già corretto in `Equipment.php`).
- Lo stesso report non includeva mai una scadenza scaduta il cui `due_date` era passato senza che nessuno avesse modificato quel record (colonna `status` non risincronizzata) — stesso bug già corretto sulla dashboard in v1.2.37, ora corretto anche qui.
## [v1.2.39] - 2026-09-24

### Fixed

- Migrazione pneumatici (v1.2.31): il caso "axle=full" con quantity 2 o 3 (raggiungibile perché il vecchio form validava asse e quantità indipendentemente) non aveva un ramo dedicato e scartava silenziosamente le gomme oltre la prima. Corretto per correttezza di eventuali repliche future della migrazione (es. da un backup precedente a v1.2.31); non recupera dati già persi durante il deploy originale.
## [v1.2.38] - 2026-09-24

### Security

- Corretta una violazione dell'isolamento tra gruppi in scrittura: le richieste di creazione/modifica di gomme, chilometraggi, guasti, scadenze, interventi e attrezzature verificavano solo che il veicolo indicato esistesse da qualche parte nel database (`exists:vehicles,id`), non che appartenesse al gruppo dell'utente — un capo/sottocapo poteva collegare dati al veicolo di un altro gruppo semplicemente indovinandone l'id. Le liste in lettura erano già correttamente filtrate per gruppo; solo la validazione in scrittura aveva questa lacuna.
## [v1.2.37] - 2026-09-24

### Fixed

- La card "Guasti"/"Stato Scadenze"/"Equipaggiamento" nella scheda veicolo: ora hanno tutte lo stesso tetto d'altezza fisso, con scroll interno oltre quel limite, invece di crescere in base al contenuto e disallinearsi tra loro.
- Dashboard: le scadenze scadute non ancora rinnovate venivano lette dalla colonna `status` persistita, risincronizzata solo alla creazione/modifica della scadenza o visitando l'elenco scadenze — una scadenza il cui `due_date` passava senza che nessuno la toccasse restava "in regola" in dashboard anche se ormai scaduta. Ora lo stato viene calcolato live come nell'elenco scadenze.
## [v1.2.36] - 2026-09-23

### Fixed

- La card "Guasti" nella scheda veicolo: lo scroll interno (rimosso in v1.2.35) torna, ma ora riempie tutta l'altezza della card invece di fermarsi a un tetto fisso in px che lasciava uno spazio vuoto sotto quando la card veniva stirata ad altezza pari con le vicine.
## [v1.2.35] - 2026-09-23

### Fixed

- La card "Guasti" nella scheda veicolo aveva uno scroll interno con altezza fissa: ora si espande fino in fondo come le altre card.

### Added

- La scheda veicolo PDF include ora una sezione "Pneumatici" (posizione, stagionalità, marca/modello, prossimo cambio, stato).
## [v1.2.34] - 2026-09-23

### Fixed

- I menu a tendina "assegna attrezzatura" introdotti in v1.2.32 comparivano sempre aperti, in linea nel flusso della pagina, invece di restare nascosti finché non si preme il pulsante "+": il progetto non importa il CSS di Bootstrap (solo il JS), e mancavano le regole `.dropdown`/`.dropdown-menu` di base che tengono il menu nascosto per default.
## [v1.2.33] - 2026-09-23

### Added

- Le tabelle raggruppate (guasti, scadenze, appuntamenti in officina) sono ora pieghevoli: un click sull'intestazione di un gruppo (es. un veicolo) nasconde/mostra le righe al suo interno, inclusi i sotto-gruppi annidati.
## [v1.2.32] - 2026-09-23

### Changed

- Assegnazione attrezzatura dalla scheda veicolo: il pulsante "+" (sia quello generale che quelli sulle righe di attrezzatura mancante) apre ora un menu a tendina con l'attrezzatura esistente assegnabile e una voce per crearne una nuova, al posto del selettore fisso in fondo alla card.
## [v1.2.31] - 2026-09-23

### Changed

- Modello dati pneumatici: ogni riga ora rappresenta una singola gomma fisica con una posizione (anteriore/posteriore sinistra/destra) invece di un "set" con asse e quantità. Un appuntamento di cambio gomme può ora riguardare 1, 2 o 4 gomme, non solo un set completo. La migrazione converte automaticamente i dati esistenti, mantenendo l'id della riga originale su una posizione così cronologia e collegamenti restano validi.

### Fixed

- Il selettore pneumatico dei guasti e il picker gomme degli appuntamenti mostravano le gomme con l'etichetta d'asse; ora mostrano la posizione specifica.
## [v1.2.30] - 2026-09-23

### Added

- Il picker delle scadenze in creazione/modifica appuntamento ora mostra anche il tempo (o i km) mancanti e un badge di stato (valida/in scadenza/scaduta), non solo tipo e data.

## [v1.2.29] - 2026-09-23

### Changed

- Il selettore pneumatico nei guasti non è più sempre visibile: compare solo dopo aver marcato il guasto come "relativo a un pneumatico".

## [v1.2.28] - 2026-09-23

### Fixed

- Badge collaudo attrezzatura mostrato come "In scadenza" anche a anni di distanza dalla scadenza reale: `diffInDays()` con Carbon 3 restituisce un valore con segno (non più sempre assoluto), e l'ordine invertito della chiamata dava un risultato negativo — sempre `<= 30` — per qualunque data futura. Stesso bug corretto anche per lo stato generale dell'attrezzatura.

### Added

- Data di scadenza attrezzatura e prossimo collaudo ora si inseriscono come mese/anno (come le scadenze), non più con un giorno specifico che non ha senso per queste date.
- Dalla scheda veicolo si può assegnare attrezzatura già esistente in anagrafica (non assegnata, o spostandola da un altro veicolo con conferma) invece di doverne creare sempre una nuova.

## [v1.2.27] - 2026-09-18

### Fixed

- Scadenze già rinnovate ancora elencate tra le "Scadenze imminenti" in dashboard: la query non escludeva le scadenze con `is_renewed=true`, quindi una revisione appena rinnovata restava visibile lì (con la vecchia data) accanto alla nuova, finché la vecchia data non passava.

## [v1.2.26] - 2026-09-18

### Fixed

- Dashboard non aggiornata dopo una mutazione (es. rinnovo di una scadenza, chiusura di un guasto, completamento di un intervento): i dati restavano quelli cachati fino a 5 minuti prima, anche se già corretti ovunque altrove nell'app. Ora ogni cambiamento rilevante invalida subito la cache della dashboard del proprio gruppo.

## [v1.2.25] - 2026-09-18

### Added

- Pagina pubblica di stato flotta: il capo può generare (dalla pagina del gruppo) un link segreto e non indovinabile che mostra, senza bisogno di account, solo quali mezzi sono disponibili o in officina — nessun dettaglio di guasti, nessun dato personale, nessuna targa. Il link è rigenerabile o disattivabile in qualsiasi momento.

## [v1.2.24] - 2026-09-19

### Added

- Scheda veicolo: la card Equipaggiamento elenca ora esplicitamente le attrezzature obbligatorie mancanti (con quante ne sono presenti rispetto a quante richieste) invece del solo badge riassuntivo in testata, con un link rapido per aggiungerle già con il tipo preselezionato.

## [v1.2.23] - 2026-09-19

### Changed

- Elenco veicoli: la riga sotto marca/modello mostra ora la targa invece di anno e alimentazione.

## [v1.2.22] - 2026-09-19

### Fixed

- Elenco veicoli: quando un veicolo aveva sia guasti aperti che in lavorazione, il badge li mostrava entrambi schiacciati su una riga ("Aperti 2 + Lavoraz. 1"). Ora sono due badge separati, uno sotto l'altro.

## [v1.2.21] - 2026-09-19

### Added

- Attrezzature: i tipi ora hanno una categoria (Estintore, Aspiratore/LSU, Sedia, Barella, DAE, LUCAS, LIFEPAK, Altro) che determina quali campi extra mostrare per gli elementi di quel tipo — marca, modello, numero identificativo e data di fabbricazione per tutti; agente estinguente, peso, collaudo e numero di revisioni per gli estintori; tipo di sedia e kg massimo per sedie e barelle.
- Nuovo storico revisioni/collaudi per attrezzatura: ogni controllo effettuato viene registrato con data e note, invece di sovrascrivere semplicemente la data corrente. Per gli estintori, il conteggio delle revisioni registrate permette di segnalare quando l'attrezzatura ha raggiunto la soglia oltre la quale va sostituita.
- Il collaudo degli estintori (retest idraulico, ciclo separato dalla revisione ordinaria) ha ora una propria data di scadenza calcolata automaticamente e un promemoria dedicato, come la revisione.
- Aggiunti i tipi di attrezzatura DAE, LUCAS, LIFEPAK e Aspiratore (LSU), già pronti all'uso.

## [v1.2.20] - 2026-09-17

### Added

- Appuntamenti "Cambio Gomme": ora si possono collegare più set di gomme insieme (es. anteriori + posteriori), non solo un set alla volta. La combinazione scelta deve coprire esattamente 4 gomme tutte della stessa stagionalità, altrimenti l'appuntamento non viene salvato. Al completamento, tutti i set collegati vengono montati insieme.
- È ora possibile raggiungere direttamente la creazione di un appuntamento "Cambio Gomme" dalla scheda del veicolo, tramite il pulsante nella card Pneumatici.

## [v1.2.19] - 2026-09-18

### Fixed

- Le date di cambio gomme stagionale (invernali/estive) erano un'unica impostazione globale condivisa da tutta l'installazione: due associazioni diverse che usano la stessa installazione erano costrette alle stesse date. Ora sono per gruppo, impostabili (solo dal capo) dalla pagina del gruppo invece che da Impostazioni.

## [v1.2.18] - 2026-09-18

### Added

- Pneumatici: l'elenco può ora essere filtrato per veicolo e per stagionalità, oltre che ordinato per veicolo, stagionalità, stato o prossimo cambio.
- Pneumatici: un set può ora riguardare solo un asse (anteriore o posteriore) invece delle 4 gomme intere, per registrare correttamente un cambio parziale. Se si sostituisce un solo asse mentre è montato un set completo, quest'ultimo viene automaticamente "diviso": l'asse non toccato resta tracciato come montato.
- Appuntamenti "Cambio Gomme": ora è possibile collegare il set di gomme da montare (scegliendo tra quelli in magazzino o descrivendone uno nuovo). Al completamento dell'appuntamento, il set collegato viene montato e viene chiesto se le gomme sostituite vanno in magazzino o dismesse.

## [v1.2.17] - 2026-09-16

### Added

- Promemoria cambio gomme stagionale: due date globali (di default 15 novembre per le invernali, 15 aprile per le estive, modificabili da Impostazioni) definiscono quando la flotta dovrebbe cambiare gomme. Un nuovo riquadro in dashboard mostra quanti veicoli l'hanno già fatto e quali sono ancora in ritardo, con un filtro "Da cambiare" nell'elenco Pneumatici per vederli subito. Chi ha attivato le notifiche riceve un avviso (in-app ed eventualmente via email) una volta per veicolo quando scatta il ritardo.

## [v1.2.16] - 2026-09-16

### Added

- Gestione pneumatici: nuova sezione "Pneumatici" per tracciare i set di gomme di ogni veicolo (estive, invernali o quattro stagioni), numero di gomme, marca/modello/misura, data e km di montaggio, e quando è previsto il prossimo cambio (per data e/o km). Il montaggio di un nuovo set aggiorna automaticamente lo storico dei cambi effettuati e riporta in magazzino il set precedente.
- I guasti possono ora essere collegati a uno specifico set di gomme del veicolo (campo opzionale), per distinguere ad esempio un problema di foratura dagli altri guasti meccanici.

## [v1.2.15] - 2026-09-16

### Added

- Le foto HEIC/HEIF caricate su un guasto vengono ora convertite in JPEG direttamente nel browser prima dell'invio: il server le accettava già da v1.2.14, ma restavano non visualizzabili in quasi tutti i browser (solo Safari sa mostrare un HEIC). La conversione avviene lato client (nessuna libreria di decodifica HEIC richiesta sul server) e scarica il decoder solo quando serve davvero, non su ogni pagina.

## [v1.2.14] - 2026-09-16

### Fixed

- Da mobile, ogni campo data/mese (Flatpickr) era completamente invisibile e non compilabile: la regola CSS che nascondeva l'input reale di Flatpickr colpiva per errore anche l'input nativo che Flatpickr stesso mostra al suo posto sui telefoni. Questo spiegava anche perché il salvataggio di un nuovo guasto da mobile non facesse nulla: la data del guasto è obbligatoria ma, essendo invisibile, non poteva essere compilata.
- Il caricamento immagini nei guasti rifiutava sempre i file HEIC/HEIF, il formato di default delle foto su iPhone.
- Dashboard, "Prossimi appuntamenti": un appuntamento di oggi spariva dall'elenco non appena passava la mezzanotte, perché la data veniva confrontata con l'orario corrente invece che con la sola data odierna.

## [v1.2.13] - 2026-09-14

### Added

- Elenco appuntamenti: filtro per veicolo (accanto alla ricerca testuale).

### Fixed

- Elenco appuntamenti: le scadenze collegate (tagliando, revisione, ecc.) non comparivano affatto nella descrizione se l'appuntamento non aveva anche un guasto collegato. Ora vengono mostrate insieme, con un badge "Scadenza" dedicato; la ricerca testuale ora trova anche il tipo di scadenza collegata.

## [v1.2.12] - 2026-09-14

### Changed

- La conferma per creare/eliminare la scadenza cinghia dopo aver attivato/disattivato "dotato di cinghia di distribuzione" ora appare come finestra modale invece che come banner in cima alla pagina, più visibile.

## [v1.2.11] - 2026-09-14

### Fixed

- Creare una "Nuova scadenza" Revisione Ministeriale/Impianto Ossigeno mentre ce n'era già una in attesa la rinnovava automaticamente e ne creava una nuova (futura) — corretto — ma il km inserito nel form finiva sulla scadenza NUOVA invece che su quella appena rinnovata, a cui quel km si riferisce davvero.

### Added

- Nuovo comando `php artisan deadlines:fix-misattached-revision-mileage [--vehicle=ID] [--apply]` per correggere i dati già esistenti affetti da questo bug.

## [v1.2.10] - 2026-09-14

### Added

- Nuovo comando `php artisan mileage-logs:timeline {vehicle}`: mostra in ordine cronologico ogni lettura km nota per un veicolo (storico chilometraggi, scadenze ministeriale/ossigeno, appuntamenti), segnalando quelle inferiori alla precedente — utile per capire un conflitto segnalato da `mileage-logs:backfill --interactive`.

## [v1.2.9] - 2026-09-14

### Added

- Modificare "dotato di cinghia di distribuzione" su un veicolo esistente ora mostra un banner di conferma sulla pagina veicolo: se attivato e non esiste ancora una scadenza cinghia, propone di crearla (calcolata dalla data di immatricolazione); se disattivato ed esiste una scadenza attiva, propone di eliminarla. Prima il flag non aveva alcun effetto se non alla creazione del veicolo.
- Nuovo comando `php artisan mileage-logs:clean-tagliando-artifacts [--vehicle=ID] [--apply]`: trova ed elimina le righe dello storico chilometraggi create dal bug corretto in v1.2.8 (km di tagliando/cinghia abbinato erroneamente alla data futura di scadenza), per chi avesse già lanciato `mileage-logs:backfill --apply` prima di quel fix.

## [v1.2.8] - 2026-09-14

### Fixed

- Lo storico chilometraggi abbinava il km di ogni scadenza (last_mileage) alla sua data di scadenza (due_date), corretto solo per Revisione Ministeriale/Impianto Ossigeno. Per tagliando/cinghia last_mileage è il km dell'ULTIMO cambio (una lettura passata), mentre due_date è la data FUTURA della prossima scadenza: l'abbinamento produceva letture false, in conflitto con quasi ogni lettura reale successiva. Per questi due tipi la lettura corretta arriva già, con la data giusta, dall'appuntamento che li genera.

## [v1.2.7] - 2026-09-14

### Fixed

- Lo storico chilometraggi trattava il valore 0 come una lettura km reale: `VehicleObserver` crea le scadenze iniziali di tagliando/cinghia con `last_mileage=0` come segnaposto "non ancora noto", producendo solo falsi conflitti con la cronologia reale (sia al salvataggio che nel comando di backfill). 0 ora viene ignorato come null.

### Added

- `mileage-logs:backfill` supporta `--interactive` (insieme a `--apply`): per ogni conflitto reale chiede come procedere — saltarlo, registrarlo comunque ignorando la cronologia, o inserire un valore km diverso — invece di poterlo solo saltare automaticamente.

## [v1.2.6] - 2026-09-14

### Added

- Nuovo comando `php artisan mileage-logs:backfill [--vehicle=ID] [--apply]`: registra nello storico chilometraggi le letture km già presenti su scadenze e appuntamenti ma mai riportate lì, per dati inseriti prima che questo collegamento esistesse.

### Fixed

- Il km inserito su una scadenza (Revisione/Tagliando/Cinghia) o su un appuntamento completato non veniva mai registrato nello storico chilometraggi del veicolo: restava isolato in quel record, non compariva come "ultimo km" nell'indice veicoli e — soprattutto — non alimentava il calcolo automatico dello stato scaduto/in scadenza per tagliando/cinghia, che confronta il km con l'ultimo letto nello storico. Ora ogni lettura viene registrata automaticamente lì, rispettando la stessa coerenza cronologica già applicata all'inserimento manuale.
- Il completamento di un appuntamento in officina aveva una propria logica di rinnovo scadenze completamente separata da quella del form di modifica, mai coperta dalle guardie anti-duplicati introdotte in precedenza: non collegava mai la scadenza successiva a quella rinnovata (renews_deadline_id), e non riportava il km rilevato all'appuntamento su ministeriale/ossigeno (solo su tagliando/cinghia). Unificata con la stessa logica del form di modifica.

## [v1.2.5] - 2026-09-14

### Fixed

- Elenco scadenze: il dropdown "tipologia" era annidato dentro il box di ricerca e ne usciva schiacciato/con doppio bordo; ora è affiancato ad esso.
- Elenco scadenze: le intestazioni dei sotto-gruppi annidati (es. tipologia dentro veicolo) avevano lo stesso stile in grassetto di quelle di primo livello, facili da confondere con un nuovo gruppo. Ora hanno uno stile più leggero e distinto, e le intestazioni di primo livello hanno un bordo di stacco dalla riga precedente.

## [v1.2.4] - 2026-09-14

### Added

- Nuovo comando `php artisan deadlines:backfill-renewal-links [--vehicle=ID] [--apply]`: collega retroattivamente le scadenze periodiche esistenti (Revisione Ministeriale/Impianto Ossigeno/Tagliando) alla scadenza che rinnovano (`renews_deadline_id`), per dati creati prima che questo collegamento esistesse. Anteprima di default, `--apply` per applicare davvero, `--vehicle` per limitare a un veicolo.
- Elenco scadenze: filtro per tipologia (dropdown, accanto ai chip di stato già esistenti).
- Elenco scadenze: i raggruppamenti "Tipologia" e "Veicolo" ("Grp" in colonna) ora sono combinabili invece di escludersi a vicenda — es. veicolo poi tipologia, annidati.

### Fixed

- La ricerca in Scadenze e Guasti non trovava nulla cercando un veicolo per codice/targa, nonostante il placeholder lo promettesse esplicitamente: cercava solo nei campi propri (tipo/stato o descrizione/stato), mai sul veicolo collegato.
- Scheda veicolo, card "Guasti": i guasti aperti/in lavorazione ora sono sempre in cima alla lista, indipendentemente dalla data (prima erano ordinati solo per data, e uno aperto più vecchio poteva finire sotto uno chiuso più recente).

## [v1.2.3] - 2026-09-14

### Fixed

- Il badge guasti aperti nella pagina veicolo mostrava il conteggio due volte (es. "⚠ 3 3 guasto/i aperto/i").

## [v1.2.2] - 2026-09-14

### Fixed

- Il fix di v1.2.1 non copriva tutti i casi: modificare la più vecchia di due Revisioni Ministeriali già rinnovate in catena (es. per aggiungere solo il km) poteva ricreare la scadenza che la rinnova già, anche se esisteva. Aggiunta una seconda guardia indipendente: se una scadenza dello stesso tipo rinnova già quella in modifica (`renews_deadline_id`), non ne viene creata un'altra, a prescindere dalla data ricalcolata.

## [v1.2.1] - 2026-09-14

### Fixed

- Riaprire in modifica una scadenza periodica (Revisione Ministeriale/Impianto Ossigeno/Tagliando) già rinnovata e salvare di nuovo (es. per annotare solo il km) creava un duplicato della scadenza successiva già generata al momento del rinnovo, invece di lasciarla invariata.
- Marcare una scadenza come rinnovata dal form di modifica non allineava più la colonna `status` a `renewed` (restava sullo stato precedente), disallineata dal flag `is_renewed` e invisibile al calcolo automatico della prossima data.

## [v1.2.0] - 2026-09-14

### Security

- **Isolamento tra gruppi applicato in modo sistematico su tutta l'applicazione.** Fino a questa versione lo scoping per gruppo esisteva solo nelle liste principali del backoffice; l'accesso a un singolo record, l'API mobile, gli export, la cache e i comandi schedulati non lo applicavano affatto, permettendo — a seconda del punto — a un utente autenticato di vedere, modificare o ricevere via email dati di un gruppo diverso dal proprio conoscendo/indovinando un id.
  - **API mobile** (`Api\VehicleController/IssueController/DeadlineController/MaintenanceRecordController`): `show()`/`index()` non filtravano per gruppo.
  - **Pannello admin**: le Policies (view/update/delete) verificavano solo il ruolo, mai l'appartenenza al gruppo del record — introdotto `HasGroupScopedAccess`, applicato a 6 Policies; scoping aggiunto anche a 5 controller `index()`, export CSV, import CSV, azioni bulk sui chilometraggi.
  - **Dashboard**: quasi nessun widget era filtrato per gruppo e il risultato era cachato sotto un'unica chiave globale, condivisa da tutti i gruppi per 5 minuti.
  - **Export PDF veicolo**: nessun controllo di gruppo sulla rotta.
  - **Notifiche schedulate ed email di riepilogo** (`app:generate-notifications`, `app:send-summary-report`): girano come comandi da console, senza utente autenticato, quindi lo scoping basato su `Auth::user()` non si applicava affatto — un capo/sottocapo riceveva notifiche ed email (con allegato PDF) su scadenze, guasti, attrezzature e appuntamenti di ogni gruppo, non solo del proprio.
- Rientrare in un proprio gruppo con il codice invito poteva retrocedere silenziosamente un capo/sottocapo a membro semplice (comportamento di `syncWithoutDetaching` sul pivot ruolo).
- Il backup del database via comando/pulsante non verificava alcuna autorizzazione.

### Fixed

- Corrette diverse N+1 query nella generazione notifiche e nell'invio report.
- Colore icona veicolo incoerente, tipi nascosti nell'elenco scadenze, 404 nell'eliminazione da pagina di dettaglio.

### Added

- Scadenze "Revisione": data di rinnovo modificabile manualmente, con catena di auto-rinnovo.

### Removed

- `RegisteredUserController`, codice morto irraggiungibile (nessuna rotta di registrazione pubblica: gli account si creano solo su invito/dal backoffice).

### Docs

- README aggiornato (versione Laravel, conteggio test, endpoint API, nota sull'assenza di registrazione pubblica).

## [v1.1.0] - 2026-09-12

### Added

- **Restyle completo "Dashboard Pro"**: tutta l'interfaccia (dashboard, veicoli, scadenze, guasti, appuntamenti, fornitori, km, attrezzature, tipi veicolo/attrezzatura, gruppi, utenti, impostazioni, notifiche, registro attività, login/auth, profilo) è stata migrata da Bootstrap al nuovo design system Dashboard Pro: sidebar + topbar, tema chiaro/scuro con switch a 3 posizioni, sidebar mobile a scomparsa, breadcrumb, tabelle e form uniformati.
- Notifiche: impostazioni (`report_email`, frequenza, giorni di preavviso, toggle `notify_on_*`) rese personali per account invece che globali; i toggle sono ora letti realmente da `GenerateNotifications`/`SendSummaryReport`, riscritti per operare per-utente.
- Pagina "Utenti" rimossa (duplicava i membri del gruppo): la creazione di un account ora avviene direttamente dalla pagina del gruppo ("Crea nuovo utente").
- Profilo utente accessibile anche cliccando su nome/avatar in fondo alla sidebar.
- Login e pagine di autenticazione: nuovo layout dedicato senza sidebar admin, occhiello mostra/nascondi password su ogni campo password dell'app.
- Scadenze: possibilità di annotare il chilometraggio anche per le revisioni (ministeriale/ossigeno), campo facoltativo e indipendente dal calcolo automatico della data.
- Appuntamenti (manutenzioni): nuovo campo "Note" facoltativo.
- Registro attività: filtro/visualizzazione basati su `subject_type` invece del sempre uguale `log_name`; il dettaglio mostra ora un confronto prima/dopo leggibile invece del JSON grezzo.

### Fixed

- Aggiunti `lang/it/auth.php` e `lang/it/passwords.php`, assenti di default in Laravel 12: i messaggi di errore di login/reset password mostravano la chiave di traduzione grezza (es. "auth.failed") invece di un testo leggibile.
- `reset-password.blade.php` inviava il form alla route sbagliata (`password.update`, autenticata, invece di `password.store`, per il link via email): il reset password da email non poteva mai funzionare.
- `GroupController::update` (rinomina gruppo) non verificava che l'utente fosse capo: qualsiasi membro poteva rinominare il gruppo chiamando la route direttamente.
- `GroupController::updateRole` non impediva di rimuovere l'ultimo capo di un gruppo.
- Rimossa `auth/register.blade.php`, vista irraggiungibile (nessuna rotta di registrazione: gli account si creano solo su invito).

### Removed

- Sezione "Token API" nel profilo nascosta (nessun client la usa: l'app mobile ottiene il proprio token da `POST /api/login`); route/controller restano attivi per un uso futuro.

## [v1.0.0] - 2026-09-09

### Added

- **E1** — Rinnovo di tutte le scadenze per selezione (tagliando, cinghia, ministeriale, ossigeno, ferie): ogni rinnovo crea un nuovo record preservando lo storico.
- **E2** — La cinghia ora si rinnova per selezione (non più per `activity_type`) con chilometraggio obbligatorio, come i tagliandi.
- **E3** — Rilevamento conflitti: gli appuntamenti sovrapposti per lo stesso veicolo vengono rifiutati con messaggio dedicato.
- **E4** — Sezione guasti risolti nascosta quando non ci sono guasti risolti da collegare all'appuntamento.
- **E5** — Badge versione nell'header, alimentato da `config('app.version')`.

### Fixed

- **B1** — `syncStatusFromRules()` ora preserva lo stato delle scadenze marcate come rinnovate (`is_renewed = true`), aggiungendo un early return.
- **B2** — `getAutomaticStatusAttribute()` e `syncStatusFromRules()` ora restituiscono `STATUS_VALID` (e non `STATUS_RENEWED`) per scadenze future fuori dal periodo di warning.
- **B3** — Allineato il default di `warning_months` a **3** (come il controller) in entrambi i punti del Model (`getAutomaticStatusAttribute()` e `syncStatusFromRules()`).
- **B4** — Aggiunto accessor `getMileageAttribute()` su `Vehicle` per restituire l'ultimo chilometraggio registrato dal log.
- **B5** — `VehicleController::update()` ora gestisce il caricamento della carta di circolazione (mancava rispetto a `store()`).
- **B6** — Sostituito `type="number"` con `type="text" inputmode="numeric"` per `internal_code` in create ed edit, per evitare perdita di zeri iniziali.

### Refactoring

- **D1** — Estratta logica di sorting/grouping in trait `SortableAndGroupable`. Refactorati `DeadlineController`, `IssueController`, `MaintenanceRecordController` e relative view.
- **D2** — Estratta logica di rilevamento duplicati in trait `DetectsDuplicates`. Refactorati `IssueController`, `MaintenanceRecordController`, `ProviderController`.
- **D3** — Estratto calcolo estensione garanzia in trait `HandlesWarrantyExtension`. Refactorati `StoreVehicleRequest`, `UpdateVehicleRequest` e `VehicleController`.
- **D4** — Rimosso `resolveStatus()` dal `DeadlineController`. Ora lo stato viene calcolato da `syncStatusFromRules()` nel Model, eliminando la duplicazione di logica.

### Performance

- **M1** — `applySorting()` ora accetta mappa con colonne DB (orderBy diretto) o callable (sorting in memoria). Ordinamento DB per `status`, `event_date`, `appointment_date`. Refactorati `IssueController` e `MaintenanceRecordController`.
- **M2** — Aggiunta paginazione (20 per pagina) in VehicleController, ProviderController, EquipmentController, MileageLogController, VehicleTypeController, EquipmentTypeController. Aggiunto supporto `paginator` al componente `x-admin.index-table`.

### Migliorie

- **M3** — Aggiunta validazione `unique` su `serial_number` in `StoreEquipmentRequest` e `UpdateEquipmentRequest`.
- **M4** — Aggiunta validazione `unique` su `name` in `StoreProviderRequest` e `UpdateProviderRequest` + migration per rendere `name` unico nel DB.
- **M5** — Aggiunto SoftDeletes a Vehicle, Issue, MaintenanceRecord, Deadline. Creata migration unica `add_soft_deletes_to_related_tables`.
- **M6** — Aggiunta colonna `role` a users (default `worker`). Create 9 Policy con trait `HasRoleBasedAccess`. Aggiunto trait `AdminOnlyAccess` a tutti i 16 FormRequest. Registrate tutte le Policy in `AppServiceProvider`.
- **M7** — Aggiunta validazione `after_or_equal:immatricolation_date` su `warranty_expiration_date` in `StoreVehicleRequest` e `UpdateVehicleRequest`.
- **M8** — Aggiunta validazione `withValidator` su `mileage` in `StoreMileageLogRequest` e `UpdateMileageLogRequest`: il nuovo chilometraggio non può essere inferiore all'ultimo registrato per lo stesso veicolo.
- **M9** — Aggiunto script JavaScript per il theme toggle (chiaro/scuro) con salvataggio in localStorage.

### Feature

- **F1** — Implementata dashboard interattiva con statistiche, scadenze imminenti, guasti aperti, prossimi appuntamenti, equipaggiamento e veicoli da attenzionare. Supporto dark mode.
- **F2** — Implementato sistema di notifiche email: comando `SendSummaryReport`, Mailable `ReportMail`, scheduler configurabile (daily/weekly/monthly), model `NotificationSetting` con CRUD per impostare email destinatario, frequenza e giorni reminder.
- **F3** — Implementato export PDF scheda veicolo con DomPDF. Controller `PdfExportController`, view con anagrafica, scadenze, guasti, dotazioni, manutenzioni e storico. Bottone "Scarica PDF" nella show del veicolo. Aggiunti accessor `status_color` su Issue, Deadline, Equipment e `open_issues` su Vehicle.
- **F4** — Implementato audit log con `spatie/laravel-activitylog`. Tracciamento automatico di creazione, modifica ed eliminazione su Vehicle, Issue, MaintenanceRecord, Deadline, Equipment. Solo modifiche effettive (`logOnlyDirty`).
