<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Smoke test per il render delle viste admin.
 *
 * Scopo: verificare che ogni pagina principale si renderizzi senza errori
 * (route esistenti, variabili passate, partial inclusi) durante il refactoring
 * UI da Bootstrap a Tailwind. Ogni pagina deve rispondere 200 senza eccezioni.
 */
class ViewRenderSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->withRole('admin')->create();
    }

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::create([
            'name' => 'Ambulanza',
            'first_inspection_months' => 12,
            'regular_inspection_months' => 12,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => now()->subYears(2),
        ]);
    }

    public function test_index_pages_render(): void
    {
        $this->createVehicle();

        $routes = [
            'admin.vehicles.index',
            'admin.vehicle-types.index',
            'admin.mileage-logs.index',
            'admin.providers.index',
            'admin.issues.index',
            'admin.maintenance-records.index',
            'admin.deadlines.index',
            'admin.equipments.index',
            'admin.equipment-types.index',
            'admin.users.index',
            'admin.groups.index',
            'admin.activity-log.index',
            'admin.settings.index',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->admin)->get(route($route));
            $response->assertOk();
        }
    }

    public function test_show_pages_render(): void
    {
        $vehicle = $this->createVehicle();
        $provider = Provider::create([
            'name' => 'Officina Test',
            'contact_info' => '+39 3885245',
            'address' => 'Via roma 2, Milano',
            'type' => 'Meccanico',
        ]);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => '2025-01',
        ]);
        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'something',
            'status' => 'closed',
            'event_date' => '2025-01-02',
        ]);
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2025/01/03',
        ]);
        $equipmentType = EquipmentType::create([
            'name' => 'Estintore',
            'first_inspection_months' => 6,
            'regular_inspection_months' => 6,
        ]);
        $equipment = Equipment::create([
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Prova',
            'serial_number' => '111111',
            'revision_date' => '2023-01-01',
            'expiration_date' => '2024-01-01',
        ]);
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($this->admin, Group::ROLE_CAPO);

        $routes = [
            ['admin.vehicles.show', $vehicle->id],
            ['admin.vehicle-types.show', $vehicle->vehicle_type_id],
            ['admin.providers.show', $provider->id],
            ['admin.issues.show', $issue->id],
            ['admin.maintenance-records.show', $maintenance->id],
            ['admin.deadlines.show', $deadline->id],
            ['admin.equipments.show', $equipment->id],
            ['admin.equipment-types.show', $equipmentType->id],
            ['admin.groups.show', $group->id],
        ];

        foreach ($routes as [$route, $id]) {
            $response = $this->actingAs($this->admin)->get(route($route, $id));
            $response->assertOk();
        }
    }

    public function test_create_pages_render(): void
    {
        $this->createVehicle();

        $routes = [
            'admin.vehicles.create',
            'admin.vehicle-types.create',
            'admin.mileage-logs.create',
            'admin.providers.create',
            'admin.issues.create',
            'admin.maintenance-records.create',
            'admin.deadlines.create',
            'admin.equipments.create',
            'admin.equipment-types.create',
            'admin.users.create',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($this->admin)->get(route($route));
            $response->assertOk();
        }
    }
}
