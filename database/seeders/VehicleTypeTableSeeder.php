<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleTypeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Ambulanza',
                'needs_oxygen_check' => true,
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ],
            [
                'name' => 'Mezzo Attrezzato',
                'needs_oxygen_check' => false,
                'first_inspection_months' => 48,
                'regular_inspection_months' => 24,
            ],
            [
                'name' => 'Auto',
                'needs_oxygen_check' => false,
                'first_inspection_months' => 48,
                'regular_inspection_months' => 24,
            ],
        ];

        foreach ($types as $type) {
            VehicleType::create($type);
        }
    }
}
