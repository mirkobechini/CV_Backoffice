<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Vehicle;

/**
 * Dati per la vista admin.vehicles.show. Estratto da
 * VehicleController::show() per tenerlo sotto le 400 righe.
 */
class VehicleShowDataService
{
    public function build(Vehicle $vehicle): array
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
        // (selezionarla la sposta qui, vedi VehicleController::assignEquipment()).
        // Esclude quella già su questo stesso veicolo.
        $assignableEquipment = Equipment::with('vehicle', 'equipmentType')
            ->where(function ($q) use ($vehicle) {
                $q->whereNull('vehicle_id')->orWhere('vehicle_id', '!=', $vehicle->id);
            })
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->orderBy('name')
            ->get();

        return compact(
            'vehicle',
            'vehicleAppointments',
            'deadlines',
            'deadlinesTypes',
            'issueProviders',
            'assignableEquipment'
        );
    }
}
