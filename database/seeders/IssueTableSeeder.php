<?php

namespace Database\Seeders;

use App\Models\Issue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class IssueTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {
    
        for ($i = 0; $i < 10; $i++) {
            Issue::create([
                'description' => $faker->sentence(),
                'status' => $faker->randomElement(['open', 'in_progress', 'closed']),
                'event_date' => $faker->date(),
                'vehicle_id' => $faker->numberBetween(1, 5),
            ]);
        }
    }
}
