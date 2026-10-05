<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VehicleScanException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanVehicleRegistrationCardRequest;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Issue;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\DeadlineService;
use App\Services\QrCodeGenerator;
use App\Services\VehicleRegistrationCardService;
use App\Services\VehicleScanService;
use App\Services\VehicleShowDataService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    public function __construct(
        private readonly DeadlineService $deadlineService,
        private readonly VehicleRegistrationCardService $registrationCardService,
        private readonly VehicleShowDataService $showDataService,
    ) {
        $this->authorizeResource(Vehicle::class, 'vehicle');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filter = $request->get('filter', 'all');

        // Usata sia dal filtro "da integrare" sia dalla stat $completeFleet
        // sotto: prima venivano eseguite due query identiche (get() di tutti
        // i veicoli con vehicleType.equipmentTypes+equipment) per calcolare
        // la stessa cosa (hasAllRequiredEquipment()) in due punti diversi —
        // una condizionata al filtro, una sempre, ad ogni caricamento pagina.
        $vehiclesWithEquipment = Vehicle::forCurrentUser()
            ->with('vehicleType.equipmentTypes', 'equipment')
            ->get();
        $completeFleet = $vehiclesWithEquipment->filter(fn($v) => $v->hasAllRequiredEquipment())->count();

        $vehicles = Vehicle::query()
            ->forCurrentUser()
            ->with(['vehicleType.equipmentTypes', 'brand', 'carModel', 'equipment', 'latestMileageLog'])
            ->with(['mileageLogs' => function ($query) {
                $query->orderByDesc('log_date')->limit(2);
            }])
            ->with(['deadlines' => function ($query) {
                $query->where('is_renewed', false)
                    ->orderBy('due_date');
            }])
            ->withCount([
                'issues as open_issues_count' => fn($query) => $query->where('status', 'open'),
                'issues as in_progress_issues_count' => fn($query) => $query->where('status', 'in_progress'),
            ])
            ->search($request->get('q'));

        // Filtri chip (Tutti, Guasti, Scadenza prossima, Da integrare)
        if ($filter === 'issues') {
            $vehicles = $vehicles->whereHas('issues', fn($q) => $q->whereIn('status', ['open', 'in_progress']));
        } elseif ($filter === 'deadline') {
            $vehicles = $vehicles->whereHas('deadlines', fn($q) => $q->where('is_renewed', false)->whereIn('status', ['pending', 'expired']));
        } elseif ($filter === 'incomplete') {
            $incompleteIds = $vehiclesWithEquipment
                ->reject(fn($v) => $v->hasAllRequiredEquipment())
                ->pluck('id');
            $vehicles = $vehicles->whereIn('id', $incompleteIds);
        }

        $vehicles = $vehicles->paginate(20);

        // Stats per la toolbar
        $totalVehicles = Vehicle::forCurrentUser()->count();
        $openIssuesCount = Issue::whereIn('status', ['open', 'in_progress'])
            ->whereHas('vehicle', fn($q) => $q->forCurrentUser())
            ->count();
        $deadlinesIn30 = Deadline::where('is_renewed', false)
            ->whereHas('vehicle', fn($q) => $q->forCurrentUser())
            ->upcoming(30)
            ->count();

        return view('admin.vehicles.index', compact(
            'vehicles',
            'totalVehicles',
            'openIssuesCount',
            'deadlinesIn30',
            'completeFleet',
            'filter'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicleTypes = VehicleType::all();

        return view('admin.vehicles.create', compact('vehicleTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreVehicleRequest $request)
    {

        $data = $request->validated();

        // Assegna il veicolo al gruppo dell'utente autenticato.
        $data['group_id'] = $request->user()->activeGroup()?->id;

        $this->registrationCardService->resolvePath($request, $data);

        $newVehicle = Vehicle::create($data);

        return redirect()->route('admin.vehicles.show', $newVehicle)->with('status', 'Veicolo creato con successo.');
    }

    /**
     * Scansiona il fronte del libretto di circolazione (contiene tutti i
     * dati anagrafici letti) tramite VehicleScanService e reindirizza al
     * form di creazione (o, se scansionato dalla pagina di modifica di un
     * veicolo esistente, al form di modifica di quel veicolo) già
     * precompilato (withInput, lo stesso meccanismo usato da una
     * validazione fallita) perché l'utente verifichi/corregga prima di
     * salvare — nessun veicolo viene creato/modificato qui.
     *
     * Viene salvato subito in una posizione "pending" prima ancora di
     * chiamare l'LLM, così resta disponibile (e riutilizzabile come carta
     * di circolazione definitiva in store()/update()) anche se l'estrazione
     * fallisce. La lettura per la scansione vera e propria avviene invece
     * dal file temporaneo dell'upload (valido solo per la durata di questa
     * richiesta): funziona indipendentemente dal disco di storage
     * configurato (locale in sviluppo, S3/R2 in produzione — vedi
     * config('filesystems.uploads_disk')), che per un disco remoto non
     * espone un percorso locale leggibile direttamente.
     */
    public function scanRegistrationCard(ScanVehicleRegistrationCardRequest $request, VehicleScanService $vehicleScanService)
    {
        $vehicle = $request->filled('vehicle_id')
            ? Vehicle::forCurrentUser()->findOrFail($request->input('vehicle_id'))
            : null;

        $this->authorize($vehicle ? 'update' : 'create', $vehicle ?? Vehicle::class);

        $targetRoute = $vehicle
            ? route('admin.vehicles.edit', $vehicle)
            : route('admin.vehicles.create');

        $frontPhoto = $request->file('photo_front');
        $frontTempPath = $frontPhoto->getRealPath();

        $frontFileName = Str::random(40) . '.' . $frontPhoto->getClientOriginalExtension();
        $frontPendingPath = $frontPhoto->storeAs('registration_cards/pending', $frontFileName, $this->registrationCardService->disk());

        try {
            $extracted = $vehicleScanService->scan([$frontTempPath]);
        } catch (VehicleScanException $e) {
            // Il messaggio mostrato all'utente resta generico (non è un
            // dettaglio su cui può agire), ma senza questo log la causa
            // reale (chiave API mancante, chiamata fallita, risposta non
            // valida...) restava impossibile da scoprire dopo il fatto.
            Log::warning("Scansione libretto fallita: {$e->getMessage()}", [
                'vehicle_id' => $vehicle?->id,
                'user_id' => $request->user()?->id,
            ]);

            return redirect()->to($targetRoute)
                ->with('error', 'Impossibile leggere il libretto: inserisci i dati manualmente.')
                ->with('scanned_registration_card_path', $frontPendingPath);
        }

        $matched = $vehicleScanService->matchBrandAndModel($extracted['brand'], $extracted['car_model']);

        $prefill = array_filter([
            'license_plate' => $extracted['license_plate'],
            'immatricolation_date' => $extracted['immatricolation_date'],
            'fuel_type' => $extracted['fuel_type'],
            'brand_id' => $matched['brand_id'],
            'car_model_id' => $matched['car_model_id'],
            'vin' => $extracted['vin'],
            'color' => $extracted['color'],
            'seats' => $extracted['seats'],
            'environmental_class' => $extracted['environmental_class'],
            'max_mass_kg' => $extracted['max_mass_kg'],
            'engine_displacement_cc' => $extracted['engine_displacement_cc'],
            'engine_power_kw' => $extracted['engine_power_kw'],
            'vehicle_category' => $extracted['vehicle_category'],
            'allowed_tire_sizes' => empty($extracted['allowed_tire_sizes']) ? null : $extracted['allowed_tire_sizes'],
            // Il libretto non distingue cinghia a secco/bagno d'olio (va
            // verificata fisicamente): "a secco" è la stima più prudente,
            // l'utente la corregge se necessario prima di salvare.
            'timing_belt_type' => $extracted['has_timing_belt_suggested'] === null
                ? null
                : ($extracted['has_timing_belt_suggested'] ? Vehicle::TIMING_BELT_TYPE_DRY_BELT : Vehicle::TIMING_BELT_TYPE_CHAIN),
        ], fn ($value) => $value !== null);

        $redirect = redirect()->to($targetRoute)
            ->withInput($prefill)
            ->with('scanned_registration_card_path', $frontPendingPath)
            ->with('status', 'Dati importati dal libretto: verifica prima di salvare.');

        if ($extracted['has_timing_belt_suggested'] !== null) {
            $redirect->with('timing_belt_suggested', true);
        }

        if (! $matched['brand_id'] && $extracted['brand']) {
            $redirect->with('unmatched_brand_text', $extracted['brand']);
        }

        if (! $matched['car_model_id'] && $extracted['car_model']) {
            $redirect->with('unmatched_model_text', $extracted['car_model']);
        }

        return $redirect;
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        return view('admin.vehicles.show', $this->showDataService->build($vehicle));
    }

    /**
     * Etichetta PDF stampabile con QR verso la scheda di questo veicolo:
     * da attaccare sul mezzo per aprirla direttamente da smartphone
     * (browser, nessuna app) — se chi scansiona non ha già una sessione
     * attiva, il login normale la riporta qui (redirect()->intended()).
     */
    public function qrLabel(Vehicle $vehicle, QrCodeGenerator $qrCodeGenerator)
    {
        $this->authorize('view', $vehicle);

        $qrSvg = $qrCodeGenerator->svg(route('admin.vehicles.show', $vehicle));

        $pdf = Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true])
            ->setPaper([0, 0, 170.08, 170.08]) // 60mm x 60mm (1mm = 2.8346pt)
            ->loadView('pdfs.qr-label', [
                'title' => $vehicle->internal_code,
                'subtitle' => $vehicle->license_plate,
                'qrSvg' => $qrSvg,
            ]);

        return $pdf->download('qr-' . $vehicle->internal_code . '.pdf');
    }

    /**
     * Assegna a questo veicolo un'attrezzatura già esistente in anagrafica,
     * non assegnata o assegnata a un altro veicolo (in tal caso la sposta
     * qui: il front-end chiede conferma prima di inviare quando
     * l'attrezzatura risulta già assegnata altrove).
     */
    public function assignEquipment(Request $request, Vehicle $vehicle)
    {
        $this->authorize('update', $vehicle);

        $data = $request->validate([
            'equipment_id' => 'required|exists:equipment,id',
        ], [
            'equipment_id.required' => "Seleziona un'attrezzatura da assegnare.",
            'equipment_id.exists' => "L'attrezzatura selezionata non esiste.",
        ]);

        $equipment = Equipment::findOrFail($data['equipment_id']);
        $this->authorize('update', $equipment);

        $equipment->update(['vehicle_id' => $vehicle->id]);

        return redirect()->route('admin.vehicles.show', $vehicle->id)->with('status', 'Attrezzatura assegnata con successo.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vehicle $vehicle)
    {
        $vehicleTypes = VehicleType::all();
        $warrantyOriginalExpirationDate = $vehicle->warranty_original_expiration_date;

        return view('admin.vehicles.edit', compact('vehicle', 'vehicleTypes', 'warrantyOriginalExpirationDate'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {

        $data = $request->validated();

        $this->registrationCardService->resolvePath($request, $data, $vehicle);

        $hadTimingBelt = $vehicle->needsTimingBeltDeadline();

        $vehicle->update($data);

        // Il tipo di distribuzione da solo non crea/elimina nulla: se è
        // appena passato da/a "catena", lo segnaliamo con un banner che
        // chiede conferma prima di creare la scadenza (calcolata dalla data
        // di immatricolazione) o di eliminare quella esistente, invece di
        // farlo in automatico.
        $this->flagTimingBeltMismatch($vehicle, $hadTimingBelt, $vehicle->needsTimingBeltDeadline());

        return redirect()->route('admin.vehicles.show', $vehicle->id)->with('status', 'Veicolo aggiornato con successo.');
    }

    /**
     * Se il tipo di distribuzione è appena passato da/a "catena", verifica
     * se il veicolo ha (o non ha) già una scadenza cinghia coerente con il
     * nuovo valore, e imposta un banner di conferma per l'azione da
     * compiere.
     */
    private function flagTimingBeltMismatch(Vehicle $vehicle, bool $before, bool $after): void
    {
        if ($before === $after) {
            return;
        }

        if ($after) {
            $alreadyExists = $vehicle->deadlines()->where('type', Deadline::TYPE_CINGHIA)->exists();

            if (! $alreadyExists) {
                session()->flash('timingBeltPrompt', ['action' => 'create', 'vehicle_id' => $vehicle->id]);
            }

            return;
        }

        $active = $vehicle->deadlines()
            ->where('type', Deadline::TYPE_CINGHIA)
            ->where('is_renewed', false)
            ->latest('due_date')
            ->first();

        if ($active) {
            session()->flash('timingBeltPrompt', [
                'action' => 'delete',
                'deadline_id' => $active->id,
                'due_date' => $active->due_date_formatted,
            ]);
        }
    }

    /**
     * Crea la scadenza cinghia iniziale per il veicolo, su conferma
     * dell'utente dal banner mostrato dopo aver impostato un tipo di
     * distribuzione diverso da "catena".
     */
    public function createTimingBeltDeadline(Vehicle $vehicle)
    {
        $this->authorize('update', $vehicle);

        $alreadyExists = $vehicle->deadlines()->where('type', Deadline::TYPE_CINGHIA)->exists();

        if (! $alreadyExists) {
            $this->deadlineService->createInitialTimingBeltDeadline($vehicle);
        }

        return redirect()->route('admin.vehicles.show', $vehicle->id)->with('status', 'Scadenza cinghia di distribuzione creata con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('delete', $vehicle);
        $vehicle->delete();

        return redirect()->route('admin.vehicles.index')->with('status', 'Veicolo eliminato con successo.');
    }
}
