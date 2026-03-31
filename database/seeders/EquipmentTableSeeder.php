<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Equipment;
use Faker\Generator as Faker;

class EquipmentTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {
        for ($i = 0; $i < 10; $i++) {
            Equipment::create([
                'equipment_type_id' => $faker->numberBetween(1, 5),
                'vehicle_id' => $faker->numberBetween(1, 5),
                'name' => $faker->word(),
                'serial_number' => $faker->unique()->regexify('[A-Z0-9]{10}'),
                'revision_date' => $faker->dateTimeBetween('-1 year', 'now'),
                'expiration_date' => $faker->dateTimeBetween('now', '+1 year'),
            ]);
        }
    }
}
