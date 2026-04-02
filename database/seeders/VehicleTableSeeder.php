<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class VehicleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {
        for ($i = 0; $i < 5; $i++) {
             Vehicle::create([
                'license_plate' => $faker->unique()->regexify('[A-Z]{2}[0-9]{3}[A-Z]{2}'),
                'internal_code' => $faker->unique()->numberBetween(1000, 9999),
                'brand_id' => $faker->numberBetween(1, 10), // Assumendo che ci siano 39 brand importati
                'car_model_id' => $faker->numberBetween(1, 17), // Assumendo che ci siano 1137 modelli importati
                'fuel_type' => $faker->randomElement(['diesel', 'benzina']),
                'vehicle_type_id' => $faker->numberBetween(1, 3),
                'immatricolation_date' => $faker->date(),
                'registration_card_path' => null,
                'warranty_expiration_date' => null,
                'has_warranty_extension' => false,
                'warranty_extension_duration' => null,
            ]);
        }   

    }
}
