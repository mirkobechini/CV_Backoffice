<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRecordIssueFormTest extends TestCase
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

    private function createVehicle(array $overrides = []): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return Vehicle::create(array_merge([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ], $overrides));
    }

    private function createProvider(): Provider
    {
        return Provider::create([
            'name' => 'Officina Test',
            'contact_info' => '+39 123456',
            'address' => 'Via Roma 1',
            'type' => 'Meccanico',
        ]);
    }

    public function test_create_form_excludes_closed_issues_already_linked(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto risolto NON collegato a un appuntamento
        $unlinkedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto non collegato',
            'status' => 'closed',
        ]);

        // Guasto risolto GIÀ collegato a un appuntamento
        $linkedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto già collegato',
            'status' => 'closed',
        ]);
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $linkedIssue->id,
            'itemable_type' => Issue::class,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // Il guasto non collegato è presente
        $response->assertSee('Guasto non collegato');
        // Il guasto già collegato NON è presente
        $response->assertDontSee('Guasto già collegato');
    }

    public function test_create_form_hides_closed_issue_section_when_all_linked(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto risolto GIÀ collegato a un appuntamento
        $linkedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto già collegato',
            'status' => 'closed',
        ]);
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $linkedIssue->id,
            'itemable_type' => Issue::class,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // La sezione guasti risolti NON deve essere renderizzata
        $response->assertDontSee('Guasti risolti (per registrare riparazioni avvenute)');
    }

    public function test_create_form_shows_dates_for_open_issues(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto con data',
            'status' => 'open',
            'event_date' => '2025-06-15',
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // La data del guasto appare accanto alla descrizione
        $response->assertSee('15/06/2025');
    }

    public function test_create_form_includes_in_progress_issue_already_linked(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto in lavorazione GIÀ collegato a un appuntamento precedente (non risolto)
        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto non risolto',
            'status' => 'in_progress',
        ]);
        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create([
            'itemable_id' => $issue->id,
            'itemable_type' => Issue::class,
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.create'));

        $response->assertOk();
        // Il guasto in lavorazione deve restare selezionabile per un nuovo appuntamento
        $response->assertSee('Guasto non risolto');
    }

    public function test_edit_form_shows_closed_issues_for_selection(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        // Guasto risolto NON collegato: deve essere selezionabile in modifica
        $closedIssue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Guasto risolto da aggiungere',
            'status' => 'closed',
            'event_date' => '2025-03-10',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.edit', $maintenance));

        $response->assertOk();
        // Il guasto risolto deve essere selezionabile in modifica
        $response->assertSee('Guasto risolto da aggiungere');
        // La data appare accanto alla descrizione
        $response->assertSee('10/03/2025');
    }

    public function test_index_shows_all_issue_descriptions(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue1 = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Primo guasto',
            'status' => 'in_progress',
        ]);
        $issue2 = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Secondo guasto',
            'status' => 'in_progress',
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create(['itemable_id' => $issue1->id, 'itemable_type' => Issue::class]);
        $maintenance->items()->create(['itemable_id' => $issue2->id, 'itemable_type' => Issue::class]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index'));

        $response->assertOk();
        // Entrambe le descrizioni dei guasti devono apparire
        $response->assertSee('Primo guasto');
        $response->assertSee('Secondo guasto');
    }

    public function test_index_shows_linked_deadlines_alongside_issues_on_the_same_row(): void
    {
        // Le scadenze collegate non comparivano affatto nell'elenco: solo i
        // guasti. Un appuntamento con guasto E scadenza deve mostrare
        // entrambi sulla stessa riga (non deve sparire nessuno dei due).
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $provider = $this->createProvider();

        $issue = Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Rumore sospetto',
            'status' => 'in_progress',
        ]);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => today()->addMonth(),
            'interval_km' => 20000,
        ]);

        $maintenance = MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
        ]);
        $maintenance->items()->create(['itemable_id' => $issue->id, 'itemable_type' => Issue::class]);
        $maintenance->items()->create(['itemable_id' => $deadline->id, 'itemable_type' => Deadline::class]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index'));

        $response->assertOk();
        $response->assertSee('Rumore sospetto');
        $response->assertSee('Tagliando');
        $response->assertSee('Scadenza'); // badge dedicato, in aggiunta a quello guasto
    }

    public function test_index_can_be_filtered_by_vehicle(): void
    {
        $user = $this->createUser();
        $vehicleA = $this->createVehicle();
        $brandB = Brand::create(['name' => 'Iveco']);
        $modelB = CarModel::create(['name' => 'Daily', 'brand_id' => $brandB->id]);
        $typeB = VehicleType::create(['name' => 'Furgone', 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicleB = Vehicle::create([
            'license_plate' => 'ZZ999ZZ',
            'internal_code' => '9999',
            'brand_id' => $brandB->id,
            'car_model_id' => $modelB->id,
            'vehicle_type_id' => $typeB->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = $this->createProvider();

        MaintenanceRecord::create([
            'vehicle_id' => $vehicleA->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Attività veicolo A',
        ]);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicleB->id,
            'provider_id' => $provider->id,
            'appointment_date' => today()->subDay(),
            'activity_type' => 'Attività veicolo B',
        ]);

        $response = $this->actingAs($user)->get(route('admin.maintenance-records.index', ['vehicle_id' => $vehicleA->id]));

        $response->assertOk();
        $response->assertSee('Attività veicolo A');
        $response->assertDontSee('Attività veicolo B');
    }
}
