<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Generator as Faker;

class ProviderTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(Faker $faker): void
    {
        for ($i = 0; $i < 5; $i++) {
            Provider::create([
                'name' => $faker->company(),
                'contact_info' => $faker->phoneNumber(),
                'address' => $faker->address(),
                'type' => $faker->randomElement(['Meccanico', 'Carrozziere', 'Gommista', 'Lavaggio', 'Allestitore']),
            ]);
        }
        
    }
}
