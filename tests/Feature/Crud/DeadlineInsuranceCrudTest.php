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

class DeadlineInsuranceCrudTest extends TestCase
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

    public function test_insurance_deadline_stores_renewal_months_field(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06-15',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'coverages' => ['RCA' => ['cost' => '850.50']],
            'insurance_renewal_months' => '6',
        ]);

        $deadline = Deadline::latest('id')->first();

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertSame(6, $deadline->insurance_renewal_months);
    }

    public function test_insurance_deadline_keeps_the_exact_day_not_end_of_month(): void
    {
        // Le assicurazioni rinnovano a un giorno preciso (polizza che
        // scade in una data esatta): a differenza delle altre tipologie
        // (solo mese/anno, spostate automaticamente a fine mese), il
        // giorno indicato non deve essere alterato.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06-15',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'coverages' => ['RCA' => ['cost' => '850.50']],
        ]);

        $deadline = Deadline::latest('id')->first();
        $this->assertSame('2026-06-15', $deadline->due_date->toDateString());
        $this->assertSame('15/06/2026', $deadline->due_date_formatted);
    }

    public function test_insurance_deadline_rejects_month_only_due_date(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $countBefore = Deadline::count();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'coverages' => ['RCA' => ['cost' => '850.50']],
        ]);

        $response->assertSessionHasErrors(['due_date']);
        $this->assertSame($countBefore, Deadline::count());
    }

    public function test_non_insurance_deadline_still_snaps_to_end_of_month(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'due_date' => '2026-06',
            'interval_km' => 20000,
        ]);

        $deadline = Deadline::latest('id')->first();
        $this->assertSame('2026-06-30', $deadline->due_date->toDateString());
        $this->assertSame('06/2026', $deadline->due_date_formatted);
    }

    public function test_insurance_deadline_requires_essential_policy_fields(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06',
        ]);

        $response->assertSessionHasErrors([
            'insurance_company',
            'insurance_policy_number',
            'coverages',
        ]);
    }

    public function test_insurance_deadline_rejects_invalid_coverage_type(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'coverages' => ['Responsabilità Civile' => ['cost' => '850.50']],
        ]);

        $response->assertSessionHasErrors(['coverages']);
    }

    /**
     * "Furto e incendio" è stato separato in due voci distinte, più
     * l'aggiunta di Infortuni conducente/Tutela legale/Eventi speciali/
     * Globale complementari (2026-10-10): verifica che il vocabolario
     * accetti tutte le nuove voci, non solo le storiche.
     */
    public function test_insurance_deadline_accepts_all_coverage_types_in_vocabulary(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $coverages = [];
        foreach (Deadline::INSURANCE_COVERAGE_TYPES as $type) {
            $coverages[$type] = ['cost' => '100.00'];
        }

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06-15',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'coverages' => $coverages,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $deadline = Deadline::latest('id')->first();
        $this->assertSame(count(Deadline::INSURANCE_COVERAGE_TYPES), $deadline->insuranceCoverages()->count());
    }

    public function test_insurance_deadline_stores_multiple_coverages_with_separate_costs(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06-15',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'coverages' => [
                'RCA' => ['cost' => '300.00'],
                'Kasko' => ['cost' => '550.50'],
            ],
            'insurance_coverage_limit' => '5000000',
            'insurance_broker_contact' => 'agenzia@example.com',
            'notes' => 'Rinnovo automatico disattivato dal broker',
        ]);

        $deadline = Deadline::latest('id')->first();

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'insurance_coverage_limit' => 5000000,
            'insurance_broker_contact' => 'agenzia@example.com',
            'notes' => 'Rinnovo automatico disattivato dal broker',
        ]);
        $this->assertDatabaseHas('deadline_insurance_coverages', [
            'deadline_id' => $deadline->id,
            'coverage_type' => 'RCA',
            'cost' => 300.00,
        ]);
        $this->assertDatabaseHas('deadline_insurance_coverages', [
            'deadline_id' => $deadline->id,
            'coverage_type' => 'Kasko',
            'cost' => 550.50,
        ]);
        $this->assertSame(850.50, $deadline->insurance_premium_total);
    }

    public function test_insurance_deadline_updates_policy_fields_and_replaces_coverages(): void
    {
        $user = $this->createUser();
        $data = $this->createDeadline();
        $deadline = $data['deadline'];
        $vehicle = $data['vehicle'];
        $deadline->insuranceCoverages()->create(['coverage_type' => 'Altro', 'cost' => 100]);

        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $deadline), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2025-01-15',
            'insurance_company' => 'Allianz',
            'insurance_policy_number' => 'POL-999',
            'coverages' => ['RCA' => ['cost' => '920.00']],
        ]);

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'insurance_company' => 'Allianz',
            'insurance_policy_number' => 'POL-999',
        ]);
        // La vecchia copertura "Altro" è stata sostituita, non accumulata.
        $this->assertDatabaseMissing('deadline_insurance_coverages', [
            'deadline_id' => $deadline->id,
            'coverage_type' => 'Altro',
        ]);
        $this->assertDatabaseHas('deadline_insurance_coverages', [
            'deadline_id' => $deadline->id,
            'coverage_type' => 'RCA',
            'cost' => 920.00,
        ]);
    }
}
