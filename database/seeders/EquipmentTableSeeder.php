<?php

namespace Database\Seeders;

use App\Models\Equipment;
use Faker\Generator as Faker;
use Illuminate\Database\Seeder;

class EquipmentTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {
        // creare observers per aggiornare stato conformità in base a scadenze
        for ($i = 0; $i < 10; $i++) {
            Equipment::create([
                'equipment_type_id' => $faker->numberBetween(1, 3),
                'vehicle_id' => $faker->numberBetween(1, 5),
                'name' => $faker->word(),
                'serial_number' => $faker->unique()->regexify('[A-Z0-9]{10}'),
                'revision_date' => $faker->dateTimeBetween('-1 year', 'now'),
                'expiration_date' => $faker->dateTimeBetween('now', '+1 year'),
            ]);
        }
    }
}
