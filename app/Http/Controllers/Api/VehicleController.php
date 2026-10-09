<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $vehicles = Vehicle::with(['brand', 'carModel', 'vehicleType'])->forCurrentUser()
            ->when($request->q, fn ($q, $search) => $q->search($search))
            ->paginate($request->per_page ?? 20);

        return response()->json($vehicles);
    }

    public function show(Request $request, Vehicle $vehicle)
    {
        // Il binding di rotta risolve il veicolo per id senza alcun filtro
        // di gruppo: senza questo controllo, un token valido di un gruppo
        // qualsiasi poteva leggere i dati di un veicolo (e i suoi guasti,
        // scadenze, attrezzature) di un altro gruppo semplicemente
        // indovinandone/incrementandone l'id. Un utente senza gruppo attivo
        // viene negato (non ristretto a nulla di preciso, ma nemmeno
        // lasciato libero): vedi audit sicurezza 2026-10-09.
        $groupId = $request->user()->activeGroup()?->id;

        if (! $groupId || $vehicle->group_id !== $groupId) {
            abort(404);
        }

        $vehicle->load(['brand', 'carModel', 'vehicleType', 'equipment.equipmentType', 'issues', 'deadlines']);

        return response()->json($vehicle);
    }
}
