<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFuelCardRequest;
use App\Http\Requests\UpdateFuelCardRequest;
use App\Models\FuelCard;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class FuelCardController extends Controller
{
    use SortableAndGroupable;

    public function __construct()
    {
        $this->authorizeResource(FuelCard::class, 'fuelCard');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sort_by' => 'nullable|in:vehicle,card_number,status',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $sortBy = $validated['sort_by'] ?? 'status';
        $sortDir = $validated['sort_dir'] ?? 'asc';

        $groupId = $request->user()->activeGroup()?->id;

        $fuelCards = $this->applySorting(
            FuelCard::with('vehicle')->where('group_id', $groupId),
            $sortBy,
            $sortDir,
            [
                'vehicle' => fn (FuelCard $c) => $c->vehicle?->internal_code ?? '',
                'card_number' => 'card_number',
                'status' => 'status',
            ]
        );

        return view('admin.fuel-cards.index', compact('fuelCards', 'sortBy', 'sortDir') + [
            'sortToggleUrl' => fn ($f) => $this->sortToggleUrl($f, $sortBy, $sortDir, 'admin.fuel-cards.index'),
            'sortIcon' => fn ($f) => $this->sortIcon($f, $sortBy, $sortDir),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::forCurrentUser()->get();

        return view('admin.fuel-cards.create', compact('vehicles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFuelCardRequest $request)
    {
        $data = $request->validated();
        $data['group_id'] = $request->user()->activeGroup()?->id;

        $fuelCard = FuelCard::create($data);

        return redirect()->route('admin.fuel-cards.show', $fuelCard)->with('status', 'Tessera creata con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(FuelCard $fuelCard)
    {
        $fuelCard->load('vehicle');

        return view('admin.fuel-cards.show', compact('fuelCard'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FuelCard $fuelCard)
    {
        $vehicles = Vehicle::forCurrentUser()->get();

        return view('admin.fuel-cards.edit', compact('fuelCard', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFuelCardRequest $request, FuelCard $fuelCard)
    {
        $fuelCard->update($request->validated());

        return redirect()->route('admin.fuel-cards.show', $fuelCard)->with('status', 'Tessera aggiornata con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FuelCard $fuelCard)
    {
        $this->authorize('delete', $fuelCard);
        $fuelCard->delete();

        return redirect()->route('admin.fuel-cards.index')->with('status', 'Tessera eliminata con successo.');
    }
}
