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
                'contact_info' => '0573 530355',
                'address' => 'Via del Girone 32, Pistoia',
                'type' => 'Meccanico',
            ],
            [
                'name' => 'Carrozzeria Due Emme',
                'contact_info' => '0573 913203',
                'address' => 'Via Provinciale Lucchese 141, Pistoia',
                'type' => 'Carrozziere',
            ],
            [
                'name' => 'L\'oasi dell\'auto',
                'contact_info' => '0574 1662014',
                'address' => 'Via Prato Snc, Agliana',
                'type' => 'Gommista',
            ],
            [
                'name' => 'Ippo wash',
                'contact_info' => '+39 344 563 7631',
                'address' => 'Via Prato 29, Agliana',
                'type' => 'Lavaggio',
            ],
            [
                'name' => 'Maf',
                'contact_info' => '0573 935009',
                'address' => 'Via Eugenio Montale, 491/493, Pistoia',
                'type' => 'Allestitore',
            ],
            [
                'name' => 'Cevi',
                'contact_info' => '0574 682481',
                'address' => 'Via del Pantano 86, Montemurlo',
                'type' => 'Allestitore',
            ],
            [
                'name' => 'Mezzani',
                'contact_info' => '0573 531622',
                'address' => 'Via Giorgio Falco, 26/40, Pistoia',
                'type' => 'Meccanico',
            ],
        ];

        foreach ($providers as $provider) {
            Provider::create($provider);
        }
    }
}
