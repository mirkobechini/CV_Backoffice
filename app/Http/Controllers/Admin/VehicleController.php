<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVehicleRequest;
use App\Http\Requests\UpdateVehicleRequest;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Issue;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\DeadlineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VehicleController extends Controller
{
    public function __construct(
        private readonly DeadlineService $deadlineService,
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
        $data['has_timing_belt'] = $request->boolean('has_timing_belt');

        // Assegna il veicolo al gruppo dell'utente autenticato.
        $data['group_id'] = $request->user()->activeGroup()?->id;

        if ($request->hasFile('registration_card')) {
            $registrationCardFile = $request->file('registration_card');
            $randomFileName = Str::random(40) . '.' . $registrationCardFile->getClientOriginalExtension();
            $data['registration_card_path'] = $registrationCardFile->storeAs('registration_cards', $randomFileName, 'public');
        }

        $newVehicle = Vehicle::create($data);

        return redirect()->route('admin.vehicles.show', $newVehicle)->with('status', 'Veicolo creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['vehicleType.equipmentTypes', 'brand', 'carModel', 'equipment.equipmentType', 'issues', 'deadlines', 'mileageLogs', 'tires']);

        $vehicleAppointments = $vehicle->maintenanceRecords()
            ->with('items.itemable', 'provider')
            ->orderByDesc('appointment_date')
            ->get();

        $deadlines = $vehicle->deadlines_grouped;
        $deadlinesTypes = Vehicle::DEADLINE_TYPES;

        // Officina collegata a ciascun guasto tramite l'appuntamento che lo referenzia,
        // usata nella card "Guasti" del dettaglio veicolo.
        $issueProviders = $vehicleAppointments
            ->filter(fn ($record) => $record->provider_id)
            ->flatMap(fn ($record) => $record->items
                ->where('itemable_type', 'App\Models\Issue')
                ->pluck('itemable_id')
                ->mapWithKeys(fn ($issueId) => [$issueId => $record->provider]))
        ;

        // Attrezzatura assegnabile a questo veicolo: quella non assegnata a
        // nessun veicolo, o già assegnata a un altro veicolo del gruppo
        // (selezionarla la sposta qui, vedi assignEquipment()). Esclude
        // quella già su questo stesso veicolo.
        $assignableEquipment = Equipment::with('vehicle', 'equipmentType')
            ->where(function ($q) use ($vehicle) {
                $q->whereNull('vehicle_id')->orWhere('vehicle_id', '!=', $vehicle->id);
            })
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->orderBy('name')
            ->get();

        return view('admin.vehicles.show', compact(
            'vehicle',
            'vehicleAppointments',
            'deadlines',
            'deadlinesTypes',
            'issueProviders',
            'assignableEquipment'
        ));
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
        $data['has_timing_belt'] = $request->boolean('has_timing_belt');

        if ($request->hasFile('registration_card')) {
            // Elimina il file precedente per evitare leak di storage
            if ($vehicle->registration_card_path) {
                Storage::disk('public')->delete($vehicle->registration_card_path);
            }

            $registrationCardFile = $request->file('registration_card');
            $randomFileName = Str::random(40) . '.' . $registrationCardFile->getClientOriginalExtension();
            $data['registration_card_path'] = $registrationCardFile->storeAs('registration_cards', $randomFileName, 'public');
        }

        $hadTimingBelt = (bool) $vehicle->has_timing_belt;

        $vehicle->update($data);

        // Il flag cinghia da solo non crea/elimina nulla: se è appena
        // cambiato, lo segnaliamo con un banner che chiede conferma prima
        // di creare la scadenza (calcolata dalla data di immatricolazione)
        // o di eliminare quella esistente, invece di farlo in automatico.
        $this->flagTimingBeltMismatch($vehicle, $hadTimingBelt, (bool) $vehicle->has_timing_belt);

        return redirect()->route('admin.vehicles.show', $vehicle->id)->with('status', 'Veicolo aggiornato con successo.');
    }

    /**
     * Se has_timing_belt è appena cambiato, verifica se il veicolo ha (o
     * non ha) già una scadenza cinghia coerente con il nuovo valore, e
     * imposta un banner di conferma per l'azione da compiere.
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
     * dell'utente dal banner mostrato dopo aver attivato has_timing_belt.
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
