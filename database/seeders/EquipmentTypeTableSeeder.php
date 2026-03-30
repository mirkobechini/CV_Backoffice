<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class EquipmentTypeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {

        for ($i = 0; $i < 5; $i++) {
            EquipmentType::create([
                'name' => $faker->unique()->word(),
                'first_inspection_months' => $faker->numberBetween(6, 24),
                'regular_inspection_months' => $faker->numberBetween(6, 24),
            ]);
        }
    }
}
