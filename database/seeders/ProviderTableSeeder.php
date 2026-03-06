<?php

namespace Database\Seeders;

use App\Models\Provider;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProviderTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'name' => 'Meacci',
                'contact_info' => '054 5303',
                'address' => 'Via del Giro 32, Pistoia',
                'type' => 'Meccanico',
            ],
            [
                'name' => 'Carrozzeria Due Emme',
                'contact_info' => '053 9132',
                'address' => 'Via Provinciale Lucce 141, Pistoia',
                'type' => 'Carrozziere',
            ],
            [
                'name' => 'L\'oasi dell\'auto',
                'contact_info' => '066 1664',
                'address' => 'Via Prato Snc, Agliana',
                'type' => 'Gommista',
            ],
            [
                'name' => 'Ippo wash',
                'contact_info' => '+44 563 31',
                'address' => 'Via Prato 29, Agliana',
                'type' => 'Lavaggio',
            ],
            [
                'name' => 'Maf',
                'contact_info' => '03 9359',
                'address' => 'Via Enio Mole, 491/493, Pistoia',
                'type' => 'Allestitore',
            ],
            [
                'name' => 'Cevi',
                'contact_info' => '05 681',
                'address' => 'Via del Pano 86, Montemurlo',
                'type' => 'Allestitore',
            ],
            [
                'name' => 'Mezzani',
                'contact_info' => '057 53162',
                'address' => 'Via Giorgio Fo, 26/40, Pisia',
                'type' => 'Meccanico',
            ],
        ];

        foreach ($providers as $provider) {
            Provider::create($provider);
        }
    }
}
