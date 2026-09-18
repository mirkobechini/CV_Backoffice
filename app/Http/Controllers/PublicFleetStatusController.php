<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\MaintenanceRecord;
use Illuminate\Support\Carbon;

/**
 * Pagina pubblica (nessuna autenticazione) di sola disponibilità flotta,
 * raggiungibile solo con il token segreto del gruppo. Espone
 * volontariamente il minimo indispensabile: nessuna descrizione di
 * guasti, nessun dato personale, nessuna targa/matricola.
 */
class PublicFleetStatusController extends Controller
{
    public function show(string $token)
    {
        $group = Group::where('public_status_token', $token)->firstOrFail();

        $vehiclesInWorkshop = MaintenanceRecord::whereHas('vehicle', fn ($q) => $q->forGroup($group->id))
            ->whereNull('return_date')
            ->where('appointment_date', '<=', Carbon::today())
            ->pluck('vehicle_id')
            ->unique();

        $vehicles = $group->vehicles()
            ->orderBy('internal_code')
            ->get(['id', 'internal_code'])
            ->map(fn ($vehicle) => [
                'internal_code' => $vehicle->internal_code,
                'available' => ! $vehiclesInWorkshop->contains($vehicle->id),
            ]);

        return view('public.fleet-status', [
            'groupName' => $group->name,
            'vehicles' => $vehicles,
        ]);
    }
}
