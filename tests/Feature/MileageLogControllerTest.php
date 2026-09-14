<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\MileageLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MileageLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * i veicoli devono appartenervi per essere visibili/accessibili
     * all'utente di questi test (le route sono scoperte per gruppo).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        return Vehicle::create(['license_plate' => 'AB123CD', 'vehicle_type_id' => $vt->id, 'internal_code' => '1234', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2024-01-01', 'group_id' => $this->defaultGroup()->id]);
    }

    public function test_index_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mileage-logs.index'))
            ->assertOk();
    }

    public function test_create_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mileage-logs.create'))
            ->assertOk();
    }

    public function test_store_creates_mileage_log(): void
    {
        $v = $this->vehicle();

        $this->actingAs($this->admin())
            ->post(route('admin.mileage-logs.store'), [
                'vehicle_id' => $v->id,
                'log_date' => '2025-01-15',
                'mileage' => 50000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mileage_logs', ['mileage' => 50000]);
    }

    public function test_show_displays_mileage_log(): void
    {
        $v = $this->vehicle();
        $log = MileageLog::create(['vehicle_id' => $v->id, 'log_date' => '2025-01-15', 'mileage' => 50000]);

        $this->actingAs($this->admin())
            ->get(route('admin.mileage-logs.show', $log))
            ->assertOk();
    }

    public function test_update_modifies_mileage_log(): void
    {
        $v = $this->vehicle();
        $log = MileageLog::create(['vehicle_id' => $v->id, 'log_date' => '2025-01-15', 'mileage' => 50000]);

        $this->actingAs($this->admin())
            ->put(route('admin.mileage-logs.update', $log), [
                'vehicle_id' => $v->id,
                'log_date' => '2025-02-01',
                'mileage' => 51000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('mileage_logs', ['mileage' => 51000]);
    }

    public function test_destroy_deletes_mileage_log(): void
    {
        $v = $this->vehicle();
        $log = MileageLog::create(['vehicle_id' => $v->id, 'log_date' => '2025-01-15', 'mileage' => 50000]);

        $this->actingAs($this->admin())
            ->delete(route('admin.mileage-logs.destroy', $log))
            ->assertRedirect();

        $this->assertDatabaseMissing('mileage_logs', ['id' => $log->id]);
    }

    public function test_bulk_create_returns_view(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.mileage-logs.bulk'))
            ->assertOk();
    }

    public function test_bulk_store_creates_multiple_logs(): void
    {
        $v1 = $this->vehicle();
        $vt2 = VehicleType::create(['name' => 'Furgone', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $v2 = Vehicle::create(['license_plate' => 'XY000ZZ', 'vehicle_type_id' => $vt2->id, 'internal_code' => '5678', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2023-06-01', 'group_id' => $this->defaultGroup()->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.mileage-logs.bulk-store'), [
                'log_date' => '2025-03-01',
                'mileages' => [
                    $v1->id => 60000,
                    $v2->id => 30000,
                ],
            ])
            ->assertRedirect(route('admin.mileage-logs.index'));

        $this->assertDatabaseHas('mileage_logs', ['vehicle_id' => $v1->id, 'mileage' => 60000]);
        $this->assertDatabaseHas('mileage_logs', ['vehicle_id' => $v2->id, 'mileage' => 30000]);
    }

    public function test_bulk_store_ignores_another_groups_vehicle_id(): void
    {
        // bulkStore() autorizza solo per classe (nessuna istanza da
        // verificare): senza forCurrentUser() sulla query di validazione, un
        // capo poteva scrivere un chilometraggio per il veicolo di un altro
        // gruppo passandone direttamente l'id.
        $otherGroup = Group::create(['name' => 'Altro Gruppo', 'invite_code' => 'ZZZZ9999']);
        $vt = VehicleType::create(['name' => 'Furgone', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $otherVehicle = Vehicle::create(['license_plate' => 'XY000ZZ', 'vehicle_type_id' => $vt->id, 'internal_code' => '5678', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2023-06-01', 'group_id' => $otherGroup->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.mileage-logs.bulk-store'), [
                'log_date' => '2025-03-01',
                'mileages' => [
                    $otherVehicle->id => 99999,
                ],
            ])
            ->assertRedirect(); // nessun id valido rimasto -> torna al form bulk

        $this->assertDatabaseMissing('mileage_logs', ['vehicle_id' => $otherVehicle->id]);
    }

    public function test_bulk_delete_ignores_another_groups_log_id(): void
    {
        $otherGroup = Group::create(['name' => 'Altro Gruppo', 'invite_code' => 'ZZZZ9999']);
        $vt = VehicleType::create(['name' => 'Furgone', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $otherVehicle = Vehicle::create(['license_plate' => 'XY000ZZ', 'vehicle_type_id' => $vt->id, 'internal_code' => '5678', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2023-06-01', 'group_id' => $otherGroup->id]);
        $otherLog = MileageLog::create(['vehicle_id' => $otherVehicle->id, 'log_date' => '2025-01-15', 'mileage' => 50000]);

        $this->actingAs($this->admin())
            ->delete(route('admin.mileage-logs.bulk-delete'), ['ids' => [$otherLog->id]])
            ->assertRedirect(route('admin.mileage-logs.index'));

        $this->assertDatabaseHas('mileage_logs', ['id' => $otherLog->id]);
    }

    public function test_pivot_save_ignores_another_groups_vehicle_id(): void
    {
        $otherGroup = Group::create(['name' => 'Altro Gruppo', 'invite_code' => 'ZZZZ9999']);
        $vt = VehicleType::create(['name' => 'Furgone', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $otherVehicle = Vehicle::create(['license_plate' => 'XY000ZZ', 'vehicle_type_id' => $vt->id, 'internal_code' => '5678', 'brand_id' => null, 'car_model_id' => null, 'fuel_type' => 'diesel', 'immatricolation_date' => '2023-06-01', 'group_id' => $otherGroup->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.mileage-logs.pivot-save'), [
                'year' => 2025,
                'mileages' => [
                    $otherVehicle->id => [3 => 12345],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('mileage_logs', ['vehicle_id' => $otherVehicle->id]);
    }
}
