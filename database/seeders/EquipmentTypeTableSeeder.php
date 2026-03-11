<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
                'regular_inspection_months' => 6
            ],

            [
                'name' => 'Barella',
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12
            ],
            [
                'name' => 'Sedia scendiscale',
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12
            ],
            [
                'name' => 'Sedia motorizzata scendiscale',
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12
            ],
            [
                'name' => 'Sedia scendiscale cingolata',
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12
            ],
        ];

        foreach ($equipmentTypes as $type) {
            EquipmentType::create($type);
        }
    }
}
