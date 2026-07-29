# 📋 Analisi Progetto: CV Backoffice

> **Data analisi:** 2026-07-28
> **Progetto:** Laravel 11 + Livewire 4 — Gestione flotta mezzi pubblica assistenza
> **Stato:** Sviluppo attivo

---

## Indice

1. [🐛 Bug](#-bug)
2. [⚠️ Duplicazioni](#️-duplicazioni)
3. [💡 Migliorie Consigliate](#-migliorie-consigliate)
4. [🏗️ Cosa è Incompleto / Mancante](#️-cosa-è-incompleto--mancante)
5. [🎯 Priorità Suggerite](#-priorità-suggerite)

---

## 🐛 Bug

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

### B5. `VehicleController::update()` ignora il file `registration_card`

**File:** `app/Http/Controllers/Admin/VehicleController.php`
**Problema:** Il metodo `store()` gestisce il caricamento della carta di circolazione, ma `update()` no. Se un utente modifica un veicolo e carica un nuovo file, questo viene ignorato.

### B6. `VehicleObserver::created()` chiama `syncStatusFromRules()` su deadline rinnovate

**File:** `app/Observers/VehicleObserver.php`
**Problema:** Quando crea deadline di backfill con `$renewed = true`, chiama `$deadline->syncStatusFromRules()` che sovrascrive lo stato (vedi B1 — ora fixato, ma il codice nell'observer potrebbe essere ridondante).

### B7. `internal_code` con `type="number"` perde gli zeri iniziali

**File:** `resources/views/admin/vehicles/create.blade.php`
**Problema:** `<input type="number">` per `internal_code` (che deve essere 4 cifre, es. "0123"). Un input numerico converte "0123" in "123" e la validazione `size:4|regex:/^[0-9]{4}$/` fallisce. Usare `type="text"` con `inputmode="numeric"`.

---

## ⚠️ Duplicazioni

### D1. Logica di ordinamento/raggruppamento identica in 3 controller

**File:** `DeadlineController`, `IssueController`, `MaintenanceRecordController`
Ognuno ha lo stesso pattern di ~50 righe per `$groupBy`, `$sortBy`, `$sortDir`, `$groupToggleUrl`, `$sortToggleUrl`, `$sortIcon`. Da estrarre in un **Trait** o in una **classe base**.

### D2. Rilevamento duplicati ripetuto in 3 controller

**File:** `IssueController::store()`, `MaintenanceRecordController::store()`, `ProviderController::store()`
Stesso pattern: query con `where('created_at', '>=', ...subMinutes(5))` per bloccare doppioni. Da estrarre in un **trait** o **request base**.

### D3. Calcolo estensione garanzia duplicato

**File:** `VehicleController::store()` e `VehicleController::update()`
Stessa logica di `has_warranty_extension` + calcolo `warrantyEffectiveExpirationDate`. Da estrarre in un metodo privato o in un **form request**.

### D4. `resolveStatus()` duplica logica del Model

**File:** `DeadlineController::resolveStatus()` e `Deadline::getAutomaticStatusAttribute()`
La logica di determinazione dello stato è implementata in due posti con leggere differenze (vedi B3).

---

## 💡 Migliorie Consigliate

### M1. Usare `orderBy()` del DB invece di Collection `sortBy()`

**Impatto:** Alto (performance)
Tutti i controller caricano tutto con `->get()` e poi ordinano in memoria con `->sortBy()`. Per poche decine di record va bene, ma con centinaia/migliaia diventa un collo di bottiglia. Usare `->orderBy()` nella query.

### M2. Aggiungere paginazione

**Impatto:** Alto (scalabilità)
Nessun controller usa `->paginate()`. Con la crescita dei dati, le liste diventeranno ingestibili.

### M3. Aggiungere `unique` validation su `serial_number` in Equipment

**File:** `StoreEquipmentRequest.php` e `UpdateEquipmentRequest.php`
La colonna `serial_number` è `unique` nel DB ma non c'è validazione lato Laravel. Un utente potrebbe ricevere un errore SQL invece di un messaggio amichevole.

### M4. Aggiungere `unique` validation su `name` in Provider

**File:** `StoreProviderRequest.php`
I nomi delle officine dovrebbero essere univoci.

### M5. Aggiungere SoftDeletes

**Impatto:** Medio
Eliminare un veicolo (`cascadeOnDelete`) cancella permanentemente guasti, manutenzioni, scadenze. Con `SoftDeletes` si potrebbe recuperare.

### M6. Aggiungere Authorization/Policy

**Impatto:** Medio (sicurezza)
Tutti i FormRequest hanno `authorize() { return true; }`. Ogni utente autenticato può fare tutto. Per un uso personale va bene, ma se in futuro ci saranno più utenti con ruoli diversi serviranno Policies.

### M7. Estrarre la logica di ordinamento in un Trait

**Impatto:** Basso (manutenibilità)
Vedi D1. Un trait `SortableAndGroupable` ridurrebbe drasticamente la duplicazione.

### M8. Aggiungere validazione `after_or_equal:immatricolation_date` su `warranty_expiration_date`

**Impatto:** Basso
La data di scadenza garanzia non può essere prima dell'immatricolazione.

### M9. Aggiungere validazione `mileage` deve essere >= ultimo log

**Impatto:** Medio
Quando si inserisce un nuovo chilometraggio, non si controlla che sia maggiore o uguale all'ultimo registrato per lo stesso veicolo.

### M10. Tema chiaro/scuro non funzionante

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

| Priorità       | Cosa           | Perché                                                                              |
| -------------- | -------------- | ----------------------------------------------------------------------------------- |
| 🔴 ~~Critico~~ | ~~B1, B2~~     | ~~Bug nella logica scadenze — dati inconsistenti~~ ✅ Fixati                        |
| **Medio**      | B6             | `VehicleObserver::created()` — ora mitigato dal fix B1, ma verificare se ridondante |
| 🔴 ~~Critico~~ | ~~B4~~         | ~~$vehicle->mileage non esiste — mostra vuoto~~ ✅ Fixato                            |
| 🟡 **Alto**    | D4             | `DeadlineController::resolveStatus()` duplica logica del Model (collegato a B3)     |
| 🟡 **Alto**    | B5             | Upload carta circolazione ignorato in edit                                          |
| 🟡 **Alto**    | M1, M2         | Performance e scalabilità                                                           |
| 🟢 **Medio**   | D1, D2, D3     | Refactoring duplicazioni                                                            |
| 🟢 **Medio**   | F1, F2         | Dashboard e notifiche                                                               |
| 🔵 **Basso**   | M3-M10, F3-F10 | Migliorie e feature secondarie                                                      |

---

> **Nota:** Questo file è stato generato automaticamente dall'analisi del codice. Puoi aggiornarlo man mano che risolvi i vari punti, spuntando ciò che hai completato.
