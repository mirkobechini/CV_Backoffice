<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTireChangeRequest;
use App\Http\Requests\StoreTireRequest;
use App\Http\Requests\UpdateTireRequest;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Services\TireSeasonService;
use Illuminate\Http\Request;

class TireController extends Controller
{
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
            'season_filter' => 'nullable|in:due',
        ]);
        $statusFilter = $validated['status_filter'] ?? 'all';
        $seasonFilter = $validated['season_filter'] ?? null;

        $query = Tire::with('vehicle.brand', 'vehicle.carModel')
            ->whereHas('vehicle', fn($q) => $q->forCurrentUser());

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
        }

        $tires = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('admin.tires.index', compact('tires', 'statusFilter', 'seasonFilter'));
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
     * Registra il montaggio di questo set di gomme sul veicolo: crea una
     * voce di storico cambio gomme, marca come "in magazzino" l'eventuale
     * set attualmente montato, e questo come "montato".
     */
    public function recordChange(StoreTireChangeRequest $request, Tire $tire)
    {
        $this->authorize('update', $tire);

        $data = $request->validated();

        $previousTire = $tire->vehicle
            ->mountedTires()
            ->where('id', '!=', $tire->id)
            ->first();

        $tire->vehicle->tireChanges()->create([
            'tire_id' => $tire->id,
            'previous_tire_id' => $previousTire?->id,
            'changed_date' => $data['changed_date'],
            'mileage_at_change' => $data['mileage_at_change'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($previousTire) {
            $previousTire->update(['status' => Tire::STATUS_STORED]);
        }

        $tire->update([
            'status' => Tire::STATUS_MOUNTED,
            'mounted_date' => $data['changed_date'],
            'mounted_mileage' => $data['mileage_at_change'] ?? $tire->mounted_mileage,
        ]);

        return redirect()->route('admin.vehicles.show', $tire->vehicle_id)->with('status', 'Cambio gomme registrato con successo.');
    }
}
