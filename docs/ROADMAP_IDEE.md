# Idee future — CV Backoffice

> Raccolta di feature proposte durante l'audit del 2026-10-04, non ancora implementate. Ordine di priorità deciso da Mirko il 2026-10-04 (vedi sezione "Ordine di lavoro concordato").

## Ordine di lavoro concordato (2026-10-04)

1. Costo manutenzioni (campo opzionale, non obbligatorio)
2. Escalation scadenze non risolte
3. Verifica automatica del backup
4. 2FA per capo/sottocapo
5. Bot Telegram o WhatsApp per le notifiche
6. Manutenzione predittiva — **da delineare meglio con Mirko prima di iniziare**, idea già pensata in passato ma probabilmente da rivedere
7. QR code su veicoli/attrezzature

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

## 6. Manutenzione predittiva — DA DELINEARE

**Idea originale**: statistica sul km/tempo medio tra guasti per tipo di componente, mostrata in dashboard, per anticipare un probabile guasto futuro.

**Stato**: Mirko aveva già pensato a questa feature in precedenza, ma ritiene che vada rivista — probabilmente per com'è definito il "funzionamento" (es. quali dati usare, come calcolare la soglia predittiva, cosa mostrare esattamente). **Da discutere insieme prima di iniziare**: non procedere senza una sessione di chiarimento su requisiti e UX.

## 7. QR code su veicoli/attrezzature

**Idea**: uno sticker con QR su ogni mezzo/attrezzatura che apre direttamente la sua scheda da smartphone (browser, non app nativa). Risolve il caso d'uso più comune di una "mobile app" (controllare stato o segnalare un guasto sul posto) con un decimo dello sforzo, zero sviluppo nativo.

---

## Altre idee emerse nell'audit, non in questo ordine di lavoro

- **API REST write + equipment**: oggi l'API è solo lettura (12 endpoint) e non copre equipment/equipment-issues/equipment-maintenance-records. Da estendere se mai un'app mobile nativa rientrasse in roadmap (oggi fuori scope).
- **Design pattern da introdurre quando si tocca di nuovo quel codice**: Strategy per le varianti cinghia (chain/dry/oil-bath) in `Deadline`/`DeadlineDueDateCalculator`; Pipeline per i passi di `MaintenanceCompletionService::complete()`; Eventi/Listener per i side-effect di completamento appuntamento invece di chiamate inline; Decorator/Strategy comune se si aggiungeranno altri formati di export oltre PDF/CSV.
