<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineCrudBasicTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
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

    private function createVehicle(): Vehicle
    {
        $brand = Brand::create([
            'name' => 'Fiat',
        ]);

        $carModel = CarModel::create([
            'name' => 'Ducato',
            'brand_id' => $brand->id,
        ]);

        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
    }

    private function createDeadline()
    {
        $vehicle = $this->createVehicle();

        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => '2025-01',
        ]);

        return compact('vehicle', 'deadline');
    }

    public function test_deadline_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.deadlines.index'));

        $response->assertStatus(200);
    }

    public function test_insurances_page_shows_only_insurance_deadlines(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $insuranceDeadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
        ]);
        $insuranceDeadline->insuranceCoverages()->create(['coverage_type' => 'RCA', 'cost' => 850.50]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'due_date' => '2026-06',
        ]);

        $response = $this->actingAs($user)->get(route('admin.deadlines.insurances'));

        $response->assertStatus(200);
        $response->assertSee('Generali');
        $response->assertSee('POL-123');
        $response->assertViewHas('deadlines', fn ($deadlines) => $deadlines->count() === 1);
    }

    public function test_index_default_view_shows_active_dry_belt_cinghia_not_the_renewed_one(): void
    {
        // La cinghia a secco ha sempre due_date nullo (scade solo a km).
        // Tra la scadenza gia' rinnovata e quella attiva nata dal
        // rinnovo, entrambe con due_date nullo, l'ordinamento/unique()
        // della vista di default (latest_revision_only) poteva scartare
        // quella attiva invece di quella storica, facendola sparire del
        // tutto dall'elenco.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $vehicle->update(['timing_belt_type' => 'dry_belt']);

        $old = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'renewed',
            'is_renewed' => true,
            'due_date' => null,
            'interval_km' => 100000,
            'last_mileage' => 50000,
        ]);

        $active = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'status' => 'pending',
            'is_renewed' => false,
            'due_date' => null,
            'interval_km' => 100000,
            'last_mileage' => 120000,
            'renews_deadline_id' => $old->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.deadlines.index'));

        $response->assertStatus(200);
        $response->assertViewHas('deadlines', function ($deadlines) use ($active, $old) {
            $ids = $deadlines->pluck('id')->all();

            return in_array($active->id, $ids, true) && ! in_array($old->id, $ids, true);
        });
    }

    public function test_deadline_create_page_is_reachable(): void
    {
        $user = $this->createUser();    // fake user

        $response = $this->actingAs($user)->get(route('admin.deadlines.create'));

        $response->assertStatus(200);
    }

    public function test_deadline_show_page_is_reachable(): void
    {
        $user = $this->createUser();    // fake user
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->get(route('admin.deadlines.show', $deadline));

        $response->assertStatus(200);
    }

    public function test_deadline_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    // fake user
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->get(route('admin.deadlines.edit', $deadline));

        $response->assertStatus(200);
    }

    public function test_deadline_can_be_stored(): void
    {
        $user = $this->createUser();    // fake user
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'status' => 'renewed',
            'due_date' => '2025-01',
        ]);

        $deadline = Deadline::latest('id')->first();

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
        ]);
    }

    public function test_deadline_can_be_updated(): void
    {
        $user = $this->createUser();
        $data = $this->createDeadline();
        $deadline = $data['deadline'];
        $vehicle = $data['vehicle'];

        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $deadline), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'status' => 'pending',
            'due_date' => '2025-01',
        ]);

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
        ]);
    }
}
