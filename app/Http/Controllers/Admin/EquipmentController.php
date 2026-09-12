<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\Equipment;
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

        $query = Equipment::with('vehicle', 'equipmentType');

        if ($q = $request->get('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('serial_number', 'like', "%{$q}%");
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
        // Preselezione veicolo quando si arriva dalla create appuntamento.
        $selectedVehicleId = request('vehicle_id');
        $equipmentTypes = EquipmentType::all();

        return view('admin.equipments.create', compact('vehicles', 'equipmentTypes', 'selectedVehicleId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEquipmentRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData = $this->resolveExpirationDate($validatedData);

        $newEquipment = Equipment::create($validatedData);

        return redirect()->route('admin.equipments.show', $newEquipment)->with('status', 'Attrezzatura creata con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Equipment $equipment)
    {
        $equipment->load('vehicle.brand', 'vehicle.carModel', 'equipmentType');

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
        $validatedData = $this->resolveExpirationDate($validatedData);

        $equipment->update($validatedData);

        return redirect()->route('admin.equipments.show', $equipment)->with('status', 'Attrezzatura aggiornata con successo.');
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
     * Remove the specified resource from storage.
     */
    public function destroy(Equipment $equipment)
    {
        $this->authorize('delete', $equipment);
        $equipment->delete();

        return redirect()->route('admin.equipments.index')->with('status', 'Attrezzatura eliminata con successo.');
    }
}
