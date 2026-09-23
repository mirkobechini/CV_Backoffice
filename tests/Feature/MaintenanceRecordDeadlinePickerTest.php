<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Il picker delle scadenze in creazione/modifica appuntamento mostrava solo
 * tipo e data: qui verifica che ci sia anche il tempo/km residuo e il
 * badge di stato (valida/in scadenza/scaduta), per decidere quale
 * scadenza far coincidere con l'appuntamento senza aprire ogni scadenza.
 */
class MaintenanceRecordDeadlinePickerTest extends TestCase
{
    use RefreshDatabase;

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    public function test_create_page_shows_days_remaining_and_status_badge_for_deadlines(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = $this->createVehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->addDays(45),
            'status' => Deadline::STATUS_VALID,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        $response->assertSee('45 gg');
        $response->assertSee('Valida');
    }

    public function test_create_page_shows_km_remaining_for_mileage_based_deadlines(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = $this->createVehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => now(), 'mileage' => 25000]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->addYear(),
            'interval_km' => 20000,
            'last_mileage' => 10000,
            'status' => Deadline::STATUS_VALID,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // Soglia 10.000 + 20.000 = 30.000, km attuali 25.000 → mancano 5.000.
        $response->assertSee('5.000 km');
    }

    public function test_edit_page_shows_status_badge_for_deadlines(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = $this->createVehicle();
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(5),
            'status' => Deadline::STATUS_EXPIRED,
        ]);
        $record = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => now(),
        ]);
        $record->items()->create([
            'itemable_id' => $deadline->id,
            'itemable_type' => Deadline::class,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.edit', $record));

        $response->assertOk();
        $response->assertSee('Scaduta');
    }
}
