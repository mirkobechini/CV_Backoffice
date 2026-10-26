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

class DeadlineFilteringTest extends TestCase
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

    public function test_deadline_type_is_required(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        $count = Deadline::count();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'status' => 'renewed',
        ]);

        // Verifica che il campo `type` sia obbligatorio.
        $response->assertSessionHasErrors(['type']);
        $this->assertDatabaseCount('deadlines', $count); // Conferma che non venga creato alcun record di scadenza senza tipo.
    }

    public function test_search_finds_deadline_by_vehicle_internal_code(): void
    {
        // Il campo cerca "tipologia o veicolo" (vedi placeholder), ma
        // Deadline::$searchable copre solo type/status: cercare il codice
        // del veicolo non trovava nulla.
        $user = $this->createUser();
        $vehicle = $this->createVehicle(); // internal_code = '1234'

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => '2025-06-30',
        ]);

        $response = $this->actingAs($user)->get(route('admin.deadlines.index', ['q' => $vehicle->internal_code]));

        $response->assertOk();
        $response->assertSee('Assicurazione');
    }

    public function test_index_can_be_filtered_by_type(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => '2025-06-30',
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'status' => 'renewed',
            'due_date' => '2025-07-31',
        ]);

        $response = $this->actingAs($user)->get(route('admin.deadlines.index', ['type_filter' => 'Assicurazione']));

        $response->assertOk();
        // "Tagliando" compare comunque come opzione nel <select> dei tipi:
        // verifichiamo la riga della tabella (span.type-name), non la sola
        // presenza testuale nella pagina.
        $response->assertSee('type-name">Assicurazione', false);
        $response->assertDontSee('type-name">Tagliando', false);
    }

    public function test_index_can_group_by_vehicle_and_type_together(): void
    {
        // Prima si poteva raggruppare solo per una chiave alla volta
        // (tipologia OPPURE veicolo): il gruppo "Grp" su tipologia e quello
        // su veicolo si escludevano a vicenda invece di potersi combinare.
        $user = $this->createUser();
        $vehicleA = $this->createVehicle();
        Deadline::create([
            'vehicle_id' => $vehicleA->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => '2025-06-30',
        ]);

        $response = $this->actingAs($user)->get(route('admin.deadlines.index', ['group_by' => 'vehicle,type']));

        $response->assertOk();
        // Livello esterno: etichetta del veicolo. Livello interno: tipologia.
        $response->assertSeeInOrder([$vehicleA->internal_code, 'Assicurazione']);
        // Il sotto-gruppo annidato ha uno stile distinto da quello del
        // gruppo principale, per non essere scambiato per un secondo
        // gruppo di primo livello.
        $response->assertSee('group-row-sub', false);
    }
}
