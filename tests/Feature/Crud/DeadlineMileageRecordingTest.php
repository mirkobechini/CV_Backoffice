<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\MileageLog;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeadlineMileageRecordingTest extends TestCase
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

    public function test_setting_mileage_on_a_ministerial_deadline_records_it_in_the_vehicle_mileage_history(): void
    {
        // Il km inserito su una scadenza restava isolato nel record: non
        // compariva come "ultimo km" del veicolo. Per ministeriale/ossigeno
        // due_date (una volta rinnovata) è la data della revisione, quindi
        // la coppia km+data è affidabile. Nessuna scadenza precedente da
        // rinnovare qui: è la prima del suo tipo per il veicolo.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Deadline::where('vehicle_id', $vehicle->id)->where('type', 'Revisione Ministeriale')->forceDelete();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2025-06',
            'last_mileage' => 45000,
            'is_renewed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('mileage_logs', [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-06-30 00:00:00',
            'mileage' => 45000,
        ]);
    }

    public function test_mileage_recorded_from_a_deadline_does_not_override_a_conflicting_later_reading(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Deadline::where('vehicle_id', $vehicle->id)->where('type', 'Revisione Ministeriale')->forceDelete();

        // Lettura successiva già presente, con km più basso.
        MileageLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-08-01',
            'mileage' => 40000,
        ]);

        // Una scadenza con data precedente ma km superiore al km futuro
        // registrato è incoerente con la cronologia: va scartata in
        // silenzio, non deve rompere il salvataggio della scadenza né
        // sovrascrivere lo storico.
        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2025-06',
            'last_mileage' => 45000,
            'is_renewed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseMissing('mileage_logs', ['mileage' => 45000]);
        $this->assertDatabaseHas('mileage_logs', ['vehicle_id' => $vehicle->id, 'mileage' => 40000]);
    }

    public function test_creating_a_new_ministerial_revision_records_mileage_against_the_renewed_one_not_the_new_one(): void
    {
        // Bug reale: creare una "Nuova scadenza" ministeriale mentre ce
        // n'era già una in attesa rinnovava quella vecchia e ne creava una
        // nuova (futura), ma il km inserito nel form finiva sulla nuova
        // (la cui data non è ancora avvenuta) invece che su quella appena
        // rinnovata — a cui quel km si riferisce davvero.
        $user = $this->createUser();
        $vehicle = $this->createVehicle(); // regular_inspection_months = 24
        Deadline::where('vehicle_id', $vehicle->id)->where('type', 'Revisione Ministeriale')->forceDelete();

        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'status' => 'pending',
            'due_date' => '2025-06-30',
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2027-06',
            'last_mileage' => 45000,
        ]);

        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('deadlines', [
            'id' => $current->id,
            'is_renewed' => true,
            'last_mileage' => 45000,
        ]);
        $newDeadline = Deadline::latest('id')->first();
        $this->assertNull($newDeadline->last_mileage);

        // La lettura va registrata alla data della scadenza rinnovata
        // (2025-06-30, quando la revisione è realmente avvenuta), non a
        // quella della nuova scadenza futura (2027-06-30).
        $this->assertDatabaseHas('mileage_logs', [
            'vehicle_id' => $vehicle->id,
            'log_date' => '2025-06-30 00:00:00',
            'mileage' => 45000,
        ]);
        $this->assertDatabaseMissing('mileage_logs', ['log_date' => '2027-06-30 00:00:00']);
    }

    public function test_setting_mileage_on_a_tagliando_deadline_does_not_record_a_false_reading(): void
    {
        // Per tagliando/cinghia, last_mileage è il km dell'ULTIMO cambio
        // (una lettura passata), mentre due_date è la data FUTURA in cui la
        // prossima scadenza è prevista: abbinarli produrrebbe una lettura
        // falsa (il km del cambio precedente spacciato per quello alla data
        // futura). Non va registrato nulla da qui — solo dall'appuntamento
        // che genera effettivamente quella lettura.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'due_date' => '2025-06',
            'last_mileage' => 45000,
            'interval_km' => 20000,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('mileage_logs', 0);
    }
}
