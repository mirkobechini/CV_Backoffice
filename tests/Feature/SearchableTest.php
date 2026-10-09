<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Issue;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchableTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vt->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);
    }

    public function test_search_filters_by_description(): void
    {
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto al motore', 'event_date' => '2025-01-01']);
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Freno bloccato', 'event_date' => '2025-02-01']);

        $results = Issue::search('motore')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Guasto al motore', $results->first()->description);
    }

    public function test_search_returns_all_when_empty(): void
    {
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto al motore', 'event_date' => '2025-01-01']);

        $results = Issue::search(null)->get();

        $this->assertCount(1, $results);
    }

    public function test_search_filters_by_status(): void
    {
        $vehicle = $this->vehicle();
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto A', 'event_date' => '2025-01-01', 'status' => 'open']);
        Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'Guasto B', 'event_date' => '2025-02-01', 'status' => 'closed']);

        $results = Issue::search('closed')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('closed', $results->first()->status);
    }

    /**
     * $fulltextable finisce direttamente in una whereRaw() (MATCH (colonna)
     * AGAINST (...)): oggi è sempre una proprietà statica scritta nel
     * modello, mai derivata dalla richiesta, ma la whitelist del formato
     * blocca comunque qualunque valore non sia un identificatore di colonna
     * sicuro, nel caso diventasse mai dinamico (audit sicurezza 2026-10-09).
     */
    public function test_search_rejects_unsafe_fulltext_column_name(): void
    {
        $model = new class extends Issue {
            protected $fulltextable = ['description) OR 1=1 -- '];
        };

        $this->expectException(\InvalidArgumentException::class);

        $model::search('qualcosa')->get();
    }
}
