<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTireChangeRequest;
use App\Http\Requests\StoreTireRequest;
use App\Http\Requests\UpdateTireRequest;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Services\TireChangeService;
use App\Services\TireSeasonService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class TireController extends Controller
{
    use SortableAndGroupable;

    public function __construct()
    {
        $this->authorizeResource(Tire::class, 'tire');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, TireSeasonService $tireSeasonService)
    {
        $validated = $request->validate([
            'status_filter' => 'nullable|in:all,mounted,stored,retired',
            'season_filter' => 'nullable|in:all,summer,winter,all_season,due',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'sort_by' => 'nullable|in:vehicle,season,status,next_change',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);
        $statusFilter = $validated['status_filter'] ?? 'all';
        $seasonFilter = $validated['season_filter'] ?? 'all';
        $vehicleId = $validated['vehicle_id'] ?? null;
        $sortBy = $validated['sort_by'] ?? null;
        $sortDir = $validated['sort_dir'] ?? 'asc';

        $query = Tire::with('vehicle.brand', 'vehicle.carModel')
            ->whereHas('vehicle', fn ($q) => $q->forCurrentUser());

        if ($q = $request->get('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('brand', 'like', "%{$q}%")
                    ->orWhere('model_name', 'like', "%{$q}%")
                    ->orWhere('size', 'like', "%{$q}%")
                    ->orWhereHas('vehicle', function ($vq) use ($q) {
                        $vq->where('internal_code', 'like', "%{$q}%")
                            ->orWhere('license_plate', 'like', "%{$q}%");
                    });
            });
        }

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        // "Da cambiare": la gomma montata la cui stagionalità non
        // corrisponde a quella attesa (le quattro stagioni sono sempre ok).
        if ($seasonFilter === 'due') {
            $expectedSeason = $tireSeasonService->expectedSeasonForGroup(auth()->user()?->activeGroup());
            $query->where('status', Tire::STATUS_MOUNTED)
                ->where('season', '!=', $expectedSeason)
                ->where('season', '!=', Tire::SEASON_ALL_SEASON);
        } elseif (in_array($seasonFilter, [Tire::SEASON_SUMMER, Tire::SEASON_WINTER, Tire::SEASON_ALL_SEASON], true)) {
            $query->where('season', $seasonFilter);
        }

        $sortMap = [
            'vehicle' => fn (Tire $t) => $t->vehicle?->internal_code ?? '',
            'season' => 'season',
            'status' => 'status',
            'next_change' => 'next_change_date',
        ];

        $allTires = $sortBy
            ? $this->applySorting($query, $sortBy, $sortDir, $sortMap)
            : $query->orderByDesc('created_at')->get();

        $perPage = 20;
        $page = (int) $request->get('page', 1);
        $tires = new LengthAwarePaginator(
            $allTires->forPage($page, $perPage)->values(),
            $allTires->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $vehiclesForFilter = Vehicle::forCurrentUser()->whereHas('tires')->get();

        return view('admin.tires.index', compact(
            'tires',
            'statusFilter',
            'seasonFilter',
            'vehicleId',
            'sortBy',
            'sortDir',
            'vehiclesForFilter'
        ) + [
            'sortToggleUrl' => fn ($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.tires.index'),
            'sortIcon' => fn ($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::forCurrentUser()->get();
        $selectedVehicleId = request('vehicle_id');

        return view('admin.tires.create', compact('vehicles', 'selectedVehicleId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTireRequest $request)
    {
        $tire = Tire::create($request->validated());

        return redirect()->route('admin.tires.show', $tire)->with('status', 'Set di gomme creato con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Tire $tire)
    {
        $tire->load('vehicle.brand', 'vehicle.carModel', 'issues', 'changes.previousTire');

        return view('admin.tires.show', compact('tire'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Tire $tire)
    {
        $vehicles = Vehicle::forCurrentUser()->get();

        return view('admin.tires.edit', compact('tire', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTireRequest $request, Tire $tire)
    {
        $tire->update($request->validated());

        return redirect()->route('admin.tires.show', $tire)->with('status', 'Set di gomme aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tire $tire)
    {
        $this->authorize('delete', $tire);
        $tire->delete();

        return redirect()->route('admin.tires.index')->with('status', 'Set di gomme eliminato con successo.');
    }

    /**
     * Registra il montaggio di questo set di gomme (o solo asse) sul
     * veicolo: vedi TireChangeService per la logica di smontaggio/split.
     */
    public function recordChange(StoreTireChangeRequest $request, Tire $tire, TireChangeService $tireChangeService)
    {
        $this->authorize('update', $tire);

        $data = $request->validated();

        $tireChangeService->recordChange(
            $tire,
            Carbon::parse($data['changed_date']),
            $data['mileage_at_change'] ?? null,
            $data['previous_disposition'],
            $data['notes'] ?? null,
        );

        return redirect()->route('admin.vehicles.show', $tire->vehicle_id)->with('status', 'Cambio gomme registrato con successo.');
    }
}
