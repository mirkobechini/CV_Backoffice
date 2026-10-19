<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentRequest;
use App\Http\Requests\UpdateEquipmentRequest;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Vehicle;
use App\Services\EquipmentRevisionDateCalculator;
use App\Services\QrCodeGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EquipmentController extends Controller
{
    use SortableAndGroupable;

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
            'group_by' => 'nullable|in:type,status,vehicle',
            'sort_by' => 'nullable|in:name,type,status,vehicle,expiration',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);
        $statusFilter = $validated['status_filter'] ?? 'all';
        $groupBy = $validated['group_by'] ?? null;
        $sortBy = $validated['sort_by'] ?? null;
        $sortDir = $validated['sort_dir'] ?? 'asc';

        // L'attrezzatura non assegnata a un veicolo (vehicle_id null) non ha
        // un gruppo proprio: resta visibile a tutti, come un veicolo senza
        // gruppo. Solo quella assegnata viene filtrata sul gruppo del veicolo.
        $query = Equipment::with('vehicle', 'equipmentType')
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')
                    ->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
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
                fn (Equipment $e) => $e->overall_status_label === $labelMap[$statusFilter]
            )->values();
        }

        $sortMap = [
            'name' => fn (Equipment $e) => mb_strtolower($e->name ?: ($e->equipmentType->name ?? '')),
            'type' => fn (Equipment $e) => mb_strtolower($e->equipmentType->name ?? ''),
            'status' => fn (Equipment $e) => $e->overall_status_label,
            'vehicle' => fn (Equipment $e) => $e->vehicle?->internal_code ?? '',
            'expiration' => fn (Equipment $e) => $e->expiration_date?->timestamp ?? PHP_INT_MAX,
        ];

        if ($sortBy) {
            $allEquipments = $this->applySortingToCollection($allEquipments, $sortBy, $sortDir, $sortMap);
        }

        // Il raggruppamento mostra sempre l'elenco completo (come per gli
        // appuntamenti): paginare spezzerebbe i gruppi a metà tra una
        // pagina e la successiva.
        if ($groupBy) {
            $groupedEquipments = $this->applyGrouping($allEquipments, $groupBy, function (Equipment $e) use ($groupBy) {
                return match ($groupBy) {
                    'type' => $e->equipmentType->name ?? 'N/A',
                    'status' => $e->overall_status_label,
                    'vehicle' => $e->vehicle?->internal_code ?? __('Non assegnata'),
                };
            });

            return view('admin.equipments.index', [
                'equipments' => $allEquipments,
                'groupedEquipments' => $groupedEquipments,
                'statusFilter' => $statusFilter,
                'groupBy' => $groupBy,
                'sortBy' => $sortBy,
                'sortDir' => $sortDir,
                'groupToggleUrl' => fn ($f) => $this->groupToggleUrl($f, $groupBy, 'admin.equipments.index'),
                'sortToggleUrl' => fn ($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.equipments.index'),
                'sortIcon' => fn ($f) => $this->sortIcon($f, $sortBy, $sortDir),
            ]);
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

        return view('admin.equipments.index', [
            'equipments' => $equipments,
            'groupedEquipments' => null,
            'statusFilter' => $statusFilter,
            'groupBy' => $groupBy,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'groupToggleUrl' => fn ($f) => $this->groupToggleUrl($f, $groupBy, 'admin.equipments.index'),
            'sortToggleUrl' => fn ($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.equipments.index'),
            'sortIcon' => fn ($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
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
        $validatedData = EquipmentRevisionDateCalculator::resolveExpirationDate($validatedData);
        $validatedData = EquipmentRevisionDateCalculator::resolveNextCollaudoDate($validatedData);

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
     * Etichetta PDF stampabile con QR verso la scheda di questa
     * attrezzatura — stesso scopo di VehicleController::qrLabel().
     */
    public function qrLabel(Equipment $equipment, QrCodeGenerator $qrCodeGenerator)
    {
        $this->authorize('view', $equipment);

        $qrSvg = $qrCodeGenerator->svg(route('admin.equipments.show', $equipment));

        $pdf = Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true])
            ->setPaper([0, 0, 170.08, 170.08]) // 60mm x 60mm (1mm = 2.8346pt)
            ->loadView('pdfs.qr-label', [
                'title' => $equipment->equipmentType?->name ?? $equipment->name,
                'subtitle' => $equipment->serial_number,
                'qrSvg' => $qrSvg,
            ]);

        return $pdf->download('qr-equipment-'.$equipment->id.'.pdf');
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
        $validatedData = EquipmentRevisionDateCalculator::resolveExpirationDate($validatedData);
        $validatedData = EquipmentRevisionDateCalculator::resolveNextCollaudoDate($validatedData);

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

            // Formato esplicito con giorno fissato a 1: createFromFormat('Y-m', ...)
            // da solo lascia il giorno al valore corrente (es. 30 se oggi è
            // il 30), e per mesi più corti (es. febbraio) quel giorno
            // trabocca già al mese successivo PRIMA di endOfMonth(), dando
            // il risultato sbagliato (es. "2032-02" -> 31 marzo invece di
            // 29 febbraio).
            $parsed = Carbon::createFromFormat('Y-m-d', $data[$field].'-01');
            $data[$field] = $parsed ? $parsed->endOfMonth()->toDateString() : null;
        }

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
