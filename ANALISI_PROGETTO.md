# 📋 Analisi Progetto: CV Backoffice

> **Data analisi:** 2026-07-28
> **Progetto:** Laravel 11 + Livewire 4 — Gestione flotta mezzi pubblica assistenza
> **Stato:** Sviluppo attivo

---

## Indice

1. [🐛 Bug](#-bug) ✅
2. [⚠️ Duplicazioni](#️-duplicazioni) ✅
3. [💡 Migliorie Consigliate](#-migliorie-consigliate)
4. [🏗️ Cosa è Incompleto / Mancante](#️-cosa-è-incompleto--mancante)
5. [🎯 Priorità Suggerite](#-priorità-suggerite)

---

## 🐛 Bug ✅

### B1. `syncStatusFromRules()` sovrascrive lo stato manuale ✅ FIXATO

**File:** `app/Models/Deadline.php`
**Problema:** `syncStatusFromRules()` NON controlla `is_renewed` prima di ricalcolare lo stato. Se una scadenza è marcata come rinnovata (`is_renewed = true`), la chiamata a `syncStatusFromRules()` la sovrascrive. Invece `getAutomaticStatusAttribute()` (usato nelle view) controlla `is_renewed` correttamente.
**Soluzione:** Aggiunto early return `if ($this->is_renewed) { return; }` all'inizio di `syncStatusFromRules()`.

### B2. `getAutomaticStatusAttribute()` — Ramo `else` finale sbagliato ✅ FIXATO

**File:** `app/Models/Deadline.php`
**Problema:** L'ultimo `else` restituisce `STATUS_RENEWED` quando la data è prima del periodo di warning. Dovrebbe restituire `STATUS_VALID`. `STATUS_RENEWED` dovrebbe venire SOLO dal flag `is_renewed`.
**Soluzione:** Sostituito `STATUS_RENEWED` con `STATUS_VALID` in `getAutomaticStatusAttribute()` e `syncStatusFromRules()`.

### B3. Default `warning_months` diverso tra Controller e Model ✅ FIXATO

**File:** `DeadlineController::resolveStatus()` vs `Deadline::getAutomaticStatusAttribute()`

- Controller: `config('deadlines.warning_months', 3)` → default **3**
- Model: `config('deadlines.warning_months', 2)` → default **2** (ora allineato a 3)
  Questo causa incongruenze: lo stato calcolato al momento della creazione può differire da quello calcolato dalle regole automatiche del model.
  **Soluzione:** Allineato il default a **3** in `getAutomaticStatusAttribute()` e `syncStatusFromRules()`.

### B4. `$vehicle->mileage` usato in view ma non esiste ✅ FIXATO

**File:** `resources/views/admin/vehicles/show.blade.php`

```blade
<strong>Chilometri:</strong> {{ $vehicle->mileage }}
```

`mileage` non è una colonna del DB né un accessor sul model `Vehicle`. I chilometri sono in `mileage_logs`. Questa riga restituirà `null`.
**Soluzione:** Aggiunto accessor `getMileageAttribute()` su `Vehicle` che restituisce l'ultimo chilometraggio da `mileageLogs`. Aggiunto `'mileageLogs'` alla eager load nel `VehicleController::show()`.

### B5. `VehicleController::update()` ignora il file `registration_card` ✅ FIXATO

**File:** `app/Http/Controllers/Admin/VehicleController.php`
**Problema:** Il metodo `store()` gestisce il caricamento della carta di circolazione, ma `update()` no. Se un utente modifica un veicolo e carica un nuovo file, questo viene ignorato.
**Soluzione:** Aggiunta la stessa logica di upload di `store()` anche in `update()`.

### B6. `internal_code` con `type="number"` perde gli zeri iniziali ✅ FIXATO

**File:** `resources/views/admin/vehicles/create.blade.php` e `edit.blade.php`
**Problema:** `<input type="number">` per `internal_code` (che deve essere 4 cifre, es. "0123"). Un input numerico converte "0123" in "123" e la validazione `size:4|regex:/^[0-9]{4}$/` fallisce.
**Soluzione:** Sostituito con `type="text" inputmode="numeric"` in entrambe le view.

---

## ⚠️ Duplicazioni ✅

### D1. Logica di ordinamento/raggruppamento identica in 3 controller ✅ FIXATO

**File:** `DeadlineController`, `IssueController`, `MaintenanceRecordController`
Ognuno ha lo stesso pattern di ~50 righe per `$groupBy`, `$sortBy`, `$sortDir`, `$groupToggleUrl`, `$sortToggleUrl`, `$sortIcon`.
**Soluzione:** Creata trait `SortableAndGroupable` in `app/Http/Controllers/Concerns/`. Refactorati tutti e 3 i controller e le relative view.

### D2. Rilevamento duplicati ripetuto in 3 controller ✅ FIXATO

**File:** `IssueController::store()`, `MaintenanceRecordController::store()`, `ProviderController::store()`
Stesso pattern: query con `where('created_at', '>=', ...subMinutes(5))` per bloccare doppioni.
**Soluzione:** Creata trait `DetectsDuplicates` in `app/Http/Controllers/Concerns/`. Refactorati tutti e 3 i controller.

### D3. Calcolo estensione garanzia duplicato ✅ FIXATO

**File:** `VehicleController::store()` e `VehicleController::update()`
Stessa logica di `has_warranty_extension` + calcolo `warrantyEffectiveExpirationDate`.
**Soluzione:** Creata trait `HandlesWarrantyExtension` in `app/Http/Requests/Concerns/`. Integrato in `StoreVehicleRequest` e `UpdateVehicleRequest`. Semplificati entrambi i metodi del controller.

### D4. `resolveStatus()` duplica logica del Model ✅ FIXATO

**File:** `DeadlineController::resolveStatus()` e `Deadline::getAutomaticStatusAttribute()`
La logica di determinazione dello stato è implementata in due posti con leggere differenze (vedi B3).
**Soluzione:** Rimosso `resolveStatus()` dal controller. Ora `store()` e `update()` non passano più `status`, lasciando che `syncStatusFromRules()` lo calcoli nel Model.

---

## 💡 Migliorie Consigliate

### M1. Usare `orderBy()` del DB invece di Collection `sortBy()` ✅ FIXATO

**Impatto:** Alto (performance)
Tutti i controller caricano tutto con `->get()` e poi ordinano in memoria con `->sortBy()`. Per poche decine di record va bene, ma con centinaia/migliaia diventa un collo di bottiglia. Usare `->orderBy()` nella query.
**Soluzione:** Modificato `applySorting()` nel trait per accettare una mappa: se il valore è una stringa (colonna DB) fa `orderBy()` direttamente, se è un callable fa sorting in memoria. `IssueController` e `MaintenanceRecordController` ora ordinano `status`, `event_date`, `appointment_date` via DB.

### M2. Aggiungere paginazione ✅ FIXATO

**Impatto:** Alto (scalabilità)
Nessun controller usa `->paginate()`. Con la crescita dei dati, le liste diventeranno ingestibili.
**Soluzione:** Sostituito `->get()`/`->all()` con `->paginate(20)` in 6 controller. Aggiunto supporto `paginator` al componente `x-admin.index-table` con `$paginator->links()`.

### M3. Aggiungere `unique` validation su `serial_number` in Equipment ✅ FIXATO

**File:** `StoreEquipmentRequest.php` e `UpdateEquipmentRequest.php`
La colonna `serial_number` è `unique` nel DB ma non c'è validazione lato Laravel. Un utente potrebbe ricevere un errore SQL invece di un messaggio amichevole.

### M4. Aggiungere `unique` validation su `name` in Provider ✅ FIXATO

**File:** `StoreProviderRequest.php` e `UpdateProviderRequest.php`
I nomi delle officine dovrebbero essere univoci.
**Soluzione:** Aggiunta validazione `unique` in entrambe le Request. Creata migration per rendere `name` unico anche a livello DB.

### M5. Aggiungere SoftDeletes ✅ FIXATO

**Impatto:** Medio
Eliminare un veicolo (`cascadeOnDelete`) cancella permanentemente guasti, manutenzioni, scadenze. Con `SoftDeletes` si potrebbe recuperare.
**Soluzione:** Creata migration unica per aggiungere `softDeletes()` a vehicles, issues, maintenance_records, deadlines. Aggiunto `use SoftDeletes` ai rispettivi Model.

### M6. Aggiungere Authorization/Policy ✅ FIXATO

**Impatto:** Medio (sicurezza)
Tutti i FormRequest hanno `authorize() { return true; }`. Ogni utente autenticato può fare tutto.
**Soluzione:** Aggiunta colonna `role` a users. Create 9 Policy con trait `HasRoleBasedAccess` (admin può tutto, altri solo visualizzazione). Creato trait `AdminOnlyAccess` per i FormRequest. Registrate le Policy in `AppServiceProvider`.

### M7. Aggiungere validazione `after_or_equal:immatricolation_date` su `warranty_expiration_date` ✅ FIXATO

**Impatto:** Basso
La data di scadenza garanzia non può essere prima dell'immatricolazione.
**Soluzione:** Aggiunta validazione `after_or_equal:immatricolation_date` e messaggio personalizzato in entrambi i FormRequest.

### M8. Aggiungere validazione `mileage` deve essere >= ultimo log

**Impatto:** Medio
Quando si inserisce un nuovo chilometraggio, non si controlla che sia maggiore o uguale all'ultimo registrato per lo stesso veicolo.

### M9. Tema chiaro/scuro non funzionante

**File:** `resources/views/layouts/app.blade.php`
Il pulsante theme toggle c'è nell'header ma non c'è lo script JavaScript per gestire il click e salvare la preferenza.

---

## 🏗️ Cosa è Incompleto / Mancante

### F1. ❌ **Dashboard vuota**

La dashboard (`/dashboard`) mostra solo "You are logged in!". Dovrebbe mostrare:

- Scadenze imminenti (prossimi 30 giorni)
- Guasti aperti
- Veicoli con equipaggiamento incompleto
- Prossimi appuntamenti in officina

### F2. ❌ **Nessun sistema di notifiche**

Non ci sono comandi schedulati, notifiche email, o alert per scadenze imminenti. Il file `routes/console.php` ha solo il default `inspire`.

### F3. ❌ **Nessun export dati**

Non c'è export in Excel/PDF per nessuna entità.

### F4. ❌ **Nessun audit log / tracciamento modifiche**

Non si sa chi ha creato/modificato/cancellato cosa.

### F5. ❌ **Collegamento manutenzione ↔ scadenza non esposto in UI**

Il campo `deadline_id` esiste in `maintenance_records` ma non è presente nei form di creazione/modifica. Quando si completa una manutenzione, la scadenza viene aggiornata solo se già collegata (tramite `complete()`), ma non c'è modo di collegarle manualmente.

### F6. ❌ **Nessun alert per equipaggiamento in scadenza**

Gli equipaggiamenti hanno `expiration_date` ma non c'è alcun sistema che avvisi quando si avvicina la scadenza.

### F7. ⚠️ **Test minimi**

Ci sono test Unit (`DeadlineBusinessLogicTest`, `VehicleBusinessLogicTest`) e Feature (CRUD), ma la copertura è bassa. Manca la logica di `complete()`, `syncStatusFromRules()`, `VehicleObserver`.

### F8. ⚠️ **Nessuna gestione utenti/ruoli**

Solo autenticazione base di Laravel Breeze. Nessun pannello admin per gestire utenti.

### F9. ⚠️ **Nessuna ricerca/filtro nelle liste**

Le index hanno raggruppamento e ordinamento ma non una barra di ricerca testuale.

### F10. ⚠️ **Mileage non integrato nel vehicle show**

I chilometraggi sono registrati ma non c'è una sezione "Ultimi chilometraggi" nella scheda veicolo, né un calcolo del totale km.

---

## 🎯 Priorità Suggerite

| Priorità                     | Cosa               | Perché                                          |
| ---------------------------- | ------------------ | ----------------------------------------------- |
| ✅ **Tutti i Bug**           | **B1-B6**          | ✅ **Risolti**                                  |
| ✅ **Tutte le Duplicazioni** | **D1-D4**          | ✅ **Risolte**                                  |
| ✅ **Performance**           | **M1-M2**          | ✅ **Ordinamento DB + Paginazione**             |
| ✅ **Migliorie**             | **M3-M5**          | ✅ **Unique validation + SoftDeletes**          |
| 🟢 **Medio**                 | F1, F2             | Dashboard e notifiche                           |
| 🔵 **Basso**                 | M6, M8-M10, F3-F10 | Authorization, validazioni, export, audit, ecc. |

---

> **Nota:** Questo file è stato generato automaticamente dall'analisi del codice. Puoi aggiornarlo man mano che risolvi i vari punti, spuntando ciò che hai completato.
