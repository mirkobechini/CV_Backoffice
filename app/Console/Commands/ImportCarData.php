<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportCarData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:car-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa brand e modelli di auto dall\'API NHTSA';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Inizio importazione dati...');

        $res = Http::get("https://raw.githubusercontent.com/matthlavacka/car-list/master/car-list.json");

        if(!$res->successful()) {
            $this->error("Impossibile scaricare i dati.");
            return;
        }

        $brandsData = $res->json();
        //Crea o aggiorna i brand necessari
        foreach ($brandsData as $brandItem) {

            $brandName = $brandItem['brand'];
            $models = $brandItem['models'];

            $this->info("Importazione brand: $brandName...");

            $brand = Brand::updateOrCreate(['name' => $brandName]);
            foreach ($models as $modelName) {
                CarModel::firstOrCreate(
                    ['name' => $modelName, 'brand_id' => $brand->id]
                );
            }
        }
        $this->info('Importazione terminata con successo!');
    }
}
