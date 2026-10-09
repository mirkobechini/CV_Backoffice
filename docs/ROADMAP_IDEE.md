# Idee future — CV Backoffice

> Raccolta di feature proposte durante l'audit del 2026-10-04, non ancora implementate. Ordine di priorità deciso da Mirko il 2026-10-04 (vedi sezione "Ordine di lavoro concordato").

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
