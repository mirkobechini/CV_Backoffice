<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\StoreEquipmentRevisionRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\Equipment;
use App\Models\EquipmentRevision;
use App\Models\EquipmentType;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EquipmentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Equipment::class, 'equipment');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'status_filter' => 'nullable|in:all,expired,pending,valid',
        ]);
        $statusFilter = $validated['status_filter'] ?? 'all';

        // L'attrezzatura non assegnata a un veicolo (vehicle_id null) non ha
        // un gruppo proprio: resta visibile a tutti, come un veicolo senza
        // gruppo. Solo quella assegnata viene filtrata sul gruppo del veicolo.
        $query = Equipment::with('vehicle', 'equipmentType')
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')
                    ->orWhereHas('vehicle', fn($vq) => $vq->forCurrentUser());
            });

        if ($q = $request->get('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%")
                    ->orWhere('brand', 'like', "%{$q}%")
                    ->orWhere('model', 'like', "%{$q}%")
                    ->orWhere('identification_number', 'like', "%{$q}%");
            });
        }

        $allEquipments = $query->get();

        // Lo stato è un accessor calcolato, non una colonna: filtriamo in memoria
        // dopo aver caricato i risultati della ricerca.
        if ($statusFilter !== 'all') {
            $labelMap = ['expired' => 'Scaduta', 'pending' => 'In scadenza', 'valid' => 'Valida'];
            $allEquipments = $allEquipments->filter(
                fn (Equipment $e) => $e->status_label === $labelMap[$statusFilter]
            )->values();
        }

        $perPage = 20;
        $page = (int) $request->get('page', 1);
        $equipments = new LengthAwarePaginator(
            $allEquipments->forPage($page, $perPage)->values(),
            $allEquipments->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.equipments.index', compact('equipments', 'statusFilter'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::forCurrentUser()->get();
        // Preselezione veicolo quando si arriva dalla create appuntamento,
        // o tipo quando si arriva dal link "attrezzatura mancante" del veicolo.
        $selectedVehicleId = request('vehicle_id');
        $selectedEquipmentTypeId = request('equipment_type_id');
        $equipmentTypes = EquipmentType::all();

        return view('admin.equipments.create', compact('vehicles', 'equipmentTypes', 'selectedVehicleId', 'selectedEquipmentTypeId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipmentRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData = $this->normalizeMonthlyDates($validatedData);
        $validatedData = $this->resolveExpirationDate($validatedData);
        $validatedData = $this->resolveNextCollaudoDate($validatedData);

        $newEquipment = Equipment::create($validatedData);

        return redirect()->route('admin.equipments.show', $newEquipment)->with('status', 'Attrezzatura creata con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Equipment $equipment)
    {
        $equipment->load('vehicle.brand', 'vehicle.carModel', 'equipmentType', 'revisions');

        return view('admin.equipments.show', compact('equipment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Equipment $equipment)
    {
        $vehicles = Vehicle::forCurrentUser()->get();
        $equipmentTypes = EquipmentType::all();

        return view('admin.equipments.edit', compact('equipment', 'vehicles', 'equipmentTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment)
    {
        $validatedData = $request->validated();
        $validatedData = $this->normalizeMonthlyDates($validatedData);
        $validatedData = $this->resolveExpirationDate($validatedData);
        $validatedData = $this->resolveNextCollaudoDate($validatedData);

        $equipment->update($validatedData);

        return redirect()->route('admin.equipments.show', $equipment)->with('status', 'Attrezzatura aggiornata con successo.');
    }

    /**
     * expiration_date e next_collaudo_date arrivano dal form come "Y-m"
     * (solo mese/anno, come le scadenze): qui si convertono nell'ultimo
     * giorno del mese per il salvataggio, prima che resolveExpirationDate/
     * resolveNextCollaudoDate valutino se serve invece il calcolo
     * automatico (che si attiva solo quando il campo è vuoto).
     */
    private function normalizeMonthlyDates(array $data): array
    {
        foreach (['expiration_date', 'next_collaudo_date'] as $field) {
            if (empty($data[$field])) {
                continue;
            }

            $parsed = Carbon::createFromFormat('Y-m', $data[$field]);
            $data[$field] = $parsed ? $parsed->endOfMonth()->toDateString() : null;
        }

        return $data;
    }

    /**
     * Calcola automaticamente la data di scadenza se non fornita, in base
     * all'intervallo di revisione periodica del tipo di attrezzatura.
     */
    private function resolveExpirationDate(array $data): array
    {
        // Se l'utente ha già fornito una data di scadenza, la rispettiamo.
        if (! empty($data['expiration_date'])) {
            return $data;
        }

        // Serve una data di revisione e un tipo con intervallo periodico.
        if (empty($data['revision_date']) || empty($data['equipment_type_id'])) {
            return $data;
        }

        $equipmentType = EquipmentType::find($data['equipment_type_id']);
        $regularMonths = $equipmentType?->regular_inspection_months;

        if (! $regularMonths || $regularMonths <= 0) {
            return $data;
        }

        $data['expiration_date'] = Carbon::parse($data['revision_date'])
            ->addMonthsNoOverflow((int) $regularMonths)
            ->toDateString();

        return $data;
    }

    /**
     * Calcola automaticamente la data del prossimo collaudo se non fornita,
     * in base all'intervallo di collaudo del tipo di attrezzatura
     * (rilevante solo per gli estintori).
     */
    private function resolveNextCollaudoDate(array $data): array
    {
        if (! empty($data['next_collaudo_date'])) {
            return $data;
        }

        if (empty($data['collaudo_date']) || empty($data['equipment_type_id'])) {
            return $data;
        }

        $equipmentType = EquipmentType::find($data['equipment_type_id']);
        $collaudoMonths = $equipmentType?->collaudo_interval_months;

        if (! $collaudoMonths || $collaudoMonths <= 0) {
            return $data;
        }

        $data['next_collaudo_date'] = Carbon::parse($data['collaudo_date'])
            ->addMonthsNoOverflow((int) $collaudoMonths)
            ->toDateString();

        return $data;
    }

    /**
     * Registra una revisione o un collaudo effettuato: crea una voce di
     * storico e aggiorna la data corrente (+ la relativa scadenza) di
     * quel tipo di controllo sull'attrezzatura.
     */
    public function recordRevision(StoreEquipmentRevisionRequest $request, Equipment $equipment)
    {
        $this->authorize('update', $equipment);

        $data = $request->validated();

        $equipment->revisions()->create($data);

        if ($data['kind'] === EquipmentRevision::KIND_COLLAUDO) {
            $update = $this->resolveNextCollaudoDate([
                'collaudo_date' => $data['performed_date'],
                'equipment_type_id' => $equipment->equipment_type_id,
            ]);
            $equipment->update([
                'collaudo_date' => $data['performed_date'],
                'next_collaudo_date' => $update['next_collaudo_date'] ?? null,
            ]);
        } else {
            $update = $this->resolveExpirationDate([
                'revision_date' => $data['performed_date'],
                'equipment_type_id' => $equipment->equipment_type_id,
            ]);
            $equipment->update([
                'revision_date' => $data['performed_date'],
                'expiration_date' => $update['expiration_date'] ?? null,
            ]);
        }

        $message = $data['kind'] === EquipmentRevision::KIND_COLLAUDO
            ? 'Collaudo registrato con successo.'
            : 'Revisione registrata con successo.';

        return redirect()->route('admin.equipments.show', $equipment)->with('status', $message);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Equipment $equipment)
    {
        $this->authorize('delete', $equipment);
        $equipment->delete();

        return redirect()->route('admin.equipments.index')->with('status', 'Attrezzatura eliminata con successo.');
    }
}
