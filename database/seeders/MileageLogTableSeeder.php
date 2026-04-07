<?php

namespace Database\Seeders;

use App\Models\MileageLog;
use App\Models\Vehicle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class MileageLogTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {
        // Assuming you have a Vehicle model and it has some records
        $vehicleIds = Vehicle::pluck('id')->toArray();

        for($i=0; $i < 10; $i++) {
            MileageLog::create([
                'vehicle_id' => $faker->randomElement($vehicleIds),
                'log_date' => $faker->date(),
                'mileage' => $faker->numberBetween(0, 100000),
            ]);
        }
    }
}
