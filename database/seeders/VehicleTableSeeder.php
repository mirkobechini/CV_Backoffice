<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VehicleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'license_plate' => 'GP365YL',
                'internal_code' => '1726',
                'brand' => 'Fiat',
                'model' => 'Ducato',
                'fuel_type' => 'diesel',
                'vehicle_type_id' => 1,
                'immatricolation_date' => '2023-09-20',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ],
            [
                'license_plate' => 'GP366YL',
                'internal_code' => '1727',
                'brand' => 'Fiat',
                'model' => 'Ducato',
                'fuel_type' => 'diesel',
                'vehicle_type_id' => 1,
                'immatricolation_date' => '2023-09-20',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ],
            [
                'license_plate' => 'GF297MR',
                'internal_code' => '1744',
                'brand' => 'Ford',
                'model' => 'Transit',
                'fuel_type' => 'diesel',
                'vehicle_type_id' => 2,
                'immatricolation_date' => '2021-11-11',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ],
            [
                'license_plate' => 'GN806AD',
                'internal_code' => '1745',
                'brand' => 'Ford',
                'model' => 'Transit custom',
                'fuel_type' => 'diesel',
                'vehicle_type_id' => 2,
                'immatricolation_date' => '2023-03-09',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ],
            [
                'license_plate' => 'GD393CN',
                'internal_code' => '1746',
                'brand' => 'Fiat',
                'model' => 'Panda',
                'fuel_type' => 'benzina',
                'vehicle_type_id' => 3,
                'immatricolation_date' => '2021-01-12',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ],
            [
                'license_plate' => 'GL620YA',
                'internal_code' => '1747',
                'brand' => 'Fiat',
                'model' => 'Doblo XL',
                'fuel_type' => 'diesel',
                'vehicle_type_id' => 2,
                'immatricolation_date' => '2022-12-30',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ],
            [
                'license_plate' => 'FB753LG',
                'internal_code' => '1748',
                'brand' => 'Fiat',
                'model' => 'Doblo',
                'fuel_type' => 'diesel',
                'vehicle_type_id' => 2,
                'immatricolation_date' => '2016-03-01',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ],
            [
                'license_plate' => 'GK034AW',
                'internal_code' => '1749',
                'brand' => 'Dacia',
                'model' => 'Sandero',
                'fuel_type' => 'benzina',
                'vehicle_type_id' => 3,
                'immatricolation_date' => '2022-07-21',
                'registration_card_path' => null,
                'warranty_original_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_expiration_date' => null,
            ]
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
