<?php

namespace App\Models\Concerns;

use App\Models\Equipment;

/**
 * Dotazione richiesta per tipo veicolo e verifica di quella presente:
 * estratto da Vehicle.php (era a 485 righe, oltre la soglia ~400 di
 * AGENTS.md) per non fargliela superare — audit qualità 2026-11-25, F4.
 */
trait HasEquipmentRequirements
{
    public function equipment()
    {
        return $this->hasMany(Equipment::class);
    }

    public function hasAllRequiredEquipment(): bool
    {
        return $this->missingRequiredEquipment()->isEmpty();
    }

    public function missingRequiredEquipment()
    {
        $this->loadMissing(['equipment', 'vehicleType.equipmentTypes']);

        $requiredEquipmentTypes = $this->vehicleType?->equipmentTypes ?? collect();

        if ($requiredEquipmentTypes->isEmpty()) {
            return collect();
        }

        // Raggruppa gli equipaggiamenti già presenti sul veicolo per equipment_type_id
        // e calcola quante unità abbiamo per ciascun tipo.
        $availableQuantities = $this->equipment
            ->groupBy('equipment_type_id')
            ->map(fn ($items) => $items->count());

        // Restituisce solo i tipi di equipaggiamento per cui la quantità disponibile
        // sul veicolo è inferiore alla quantità richiesta dal pivot required_quantity.
        return $requiredEquipmentTypes->filter(function ($equipmentType) use ($availableQuantities) {
            $requiredQuantity = (int) $equipmentType->pivot->required_quantity;
            $actualQuantity = (int) ($availableQuantities[$equipmentType->id] ?? 0);

            return $actualQuantity < $requiredQuantity;
        });
    }
}
