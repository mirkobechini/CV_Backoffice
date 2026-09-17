<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

class EquipmentTypeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $equipmentTypes = [
            [
                'name' => 'Estintore',
                'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
                'first_inspection_months' => 6,
                'regular_inspection_months' => 6,
            ],
            [
                'name' => 'Barella',
                'category' => EquipmentType::CATEGORY_STRETCHER,
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ],
            [
                'name' => 'Seggiola',
                'category' => EquipmentType::CATEGORY_CHAIR,
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ],
            [
                'name' => 'DAE',
                'category' => EquipmentType::CATEGORY_DAE,
            ],
            [
                'name' => 'LUCAS',
                'category' => EquipmentType::CATEGORY_LUCAS,
            ],
            [
                'name' => 'LIFEPAK',
                'category' => EquipmentType::CATEGORY_LIFEPAK,
            ],
            [
                'name' => 'Aspiratore (LSU)',
                'category' => EquipmentType::CATEGORY_LSU,
            ],
        ];

        foreach ($equipmentTypes as $type) {
            EquipmentType::create($type);
        }
    }
}
