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
                'first_inspection_months' => 6,
                'regular_inspection_months' => 6,
            ],
            [
                'name' => 'Barella',
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ],
            [
                'name' => 'Seggiola',
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ],
        ];

        foreach ($equipmentTypes as $type) {
            EquipmentType::create($type);
        }
    }
}
