<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use App\Models\VehicleType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleTypeEquipmentRequirementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ambulanza = VehicleType::where('name', 'Ambulanza')->first();
        $mezzoAttrezzato = VehicleType::where('name', 'Mezzo Attrezzato')->first();
        $auto = VehicleType::where('name', 'Auto')->first();

        $estintore = EquipmentType::where('name', 'Estintore')->first();
        $barella = EquipmentType::where('name', 'Barella')->first();
        $seggiola = EquipmentType::where('name', 'Seggiola')->first();

        $ambulanza->equipmentTypes()->syncWithoutDetaching([
            $estintore->id => ['required_quantity' => 2],
            $barella->id => ['required_quantity' => 1],
            $seggiola->id => ['required_quantity' => 1],
        ]);

        $mezzoAttrezzato->equipmentTypes()->syncWithoutDetaching([
            $estintore->id => ['required_quantity' => 1],
        ]);

        $auto->equipmentTypes()->syncWithoutDetaching([
            $estintore->id => ['required_quantity' => 1],
        ]);
    }
}
