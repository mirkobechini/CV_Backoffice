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
        $brandsNeeded = ['FIAT', 'FORD', 'RENAULT', 'MERCEDES-BENZ', 'DACIA', 'VOLKSWAGEN'];

        $this->info('Inizio importazione dati...');

        //Crea o aggiorna i brand necessari
        foreach ($brandsNeeded as $brandName) {
            $this->info("Importando brand: $brandName");
            $brand = Brand::updateOrCreate(['name' => $brandName]);

            //Chiama API per modelli del brand
            $res = Http::get("https://vpic.nhtsa.dot.gov/api/vehicles/getmodelsformake/$brandName?format=json");
            if ($res->successful()) {
                $carModels = $res->json()['Results'];

                foreach ($carModels as $carModelData) {
                    CarModel::updateOrCreate([
                        'brand_id' => $brand->id,
                        'name' => $carModelData['Model_Name']
                    ]);
                }
                $this->info("Completato: $brandName (" . count($carModels) . " modelli aggiunti)");
            } else {
                $this->error("Errore durante il recupero dei modelli per $brandName");
            }
        }
        $this->info('Importazione terminata con successo!');
    }
}
