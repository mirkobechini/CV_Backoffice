<?php

namespace Database\Seeders;

use App\Models\vehicleType;
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
                'extinguishers_required' => 2,
                'first_inspection_months' => 12,
                'regular_inspection_months' => 12,
            ],
            [
                'name' => 'Mezzo Attrezzato',
                'needs_oxygen_check' => false,
                'extinguishers_required' => 1,
                'first_inspection_months' => 48,
                'regular_inspection_months' => 24,
            ],
            [
                'name' => 'Auto',
                'needs_oxygen_check' => false,
                'extinguishers_required' => 1,
                'first_inspection_months' => 48,
                'regular_inspection_months' => 24,
            ],
        ];

        foreach ($types as $type) {
            vehicleType::create($type);
        }
    }
}
