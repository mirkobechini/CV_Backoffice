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

class DeadlineRenewalViaUpdateTest extends TestCase
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

    public function test_marking_ministerial_deadline_as_renewed_via_update_creates_next_occurrence(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle(); // regular_inspection_months = 24
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'status' => 'pending',
            'due_date' => '2025-06-30',
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $current), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2025-06',
            'is_renewed' => '1',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('deadlines', [
            'id' => $current->id,
            'is_renewed' => true,
            'status' => 'renewed',
        ]);
        // La prossima scadenza va calcolata dalla data di QUESTA revisione
        // (2025-06-30 + 24 mesi), non da un fallback su altri dati.
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2027-06-30 00:00:00',
            'renews_deadline_id' => $current->id,
        ]);
        $this->assertEquals(2, Deadline::where('vehicle_id', $vehicle->id)->count());
    }

    public function test_marking_tagliando_deadline_as_renewed_via_update_sets_km_interval_on_next_occurrence(): void
    {
        // Stesso calcolo già verificato per il rinnovo senza appuntamento
        // (DeadlineRenewWithoutAppointmentTest): qui passa dal form di
        // modifica di una scadenza esistente (DeadlineService::createNextDeadlineAfterRenewal),
        // che prima di C11 non impostava interval_km/last_mileage sulla
        // nuova scadenza.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'status' => 'pending',
            'due_date' => '2025-06-30',
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $current), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'due_date' => '2025-06',
            'is_renewed' => '1',
            'last_mileage' => 50000,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'renews_deadline_id' => $current->id,
            'last_mileage' => 50000,
            'interval_km' => 20000,
        ]);
    }

    public function test_marking_insurance_deadline_as_renewed_via_update_creates_next_occurrence(): void
    {
        // Audit 2026-10-22 (B2): rinnovare un'assicurazione dalla pagina di
        // modifica (invece che da "Rinnova adesso") segnava la vecchia come
        // rinnovata ma non creava mai la successiva — il veicolo restava
        // silenziosamente senza assicurazione attiva.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'pending',
            'due_date' => '2025-06-30',
            'is_renewed' => false,
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'insurance_renewal_months' => 12,
        ]);
        $current->insuranceCoverages()->create(['coverage_type' => 'RCA', 'cost' => 850.50]);

        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $current), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2025-06-30',
            'is_renewed' => '1',
            'insurance_company' => 'Generali',
            'insurance_policy_number' => 'POL-123',
            'insurance_renewal_months' => 12,
            'coverages' => ['RCA' => ['cost' => '850.50']],
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('deadlines', [
            'id' => $current->id,
            'is_renewed' => true,
            'status' => 'renewed',
        ]);
        $this->assertDatabaseHas('deadlines', [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'due_date' => '2026-06-30 00:00:00',
            'renews_deadline_id' => $current->id,
            'insurance_company' => 'Generali',
        ]);
        $this->assertEquals(2, Deadline::where('vehicle_id', $vehicle->id)->count());

        $next = Deadline::where('renews_deadline_id', $current->id)->first();
        $this->assertSame('RCA', $next->insuranceCoverages->first()->coverage_type);
    }

    public function test_editing_an_already_renewed_deadline_does_not_duplicate_next_occurrence(): void
    {
        // Bug reale: riaprire in modifica una scadenza GIÀ rinnovata (es.
        // per annotare solo il km di riferimento) rieseguiva la creazione
        // della scadenza successiva, duplicandola.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'status' => 'pending',
            'due_date' => '2025-06-30',
            'is_renewed' => false,
        ]);

        $this->actingAs($user)->put(route('admin.deadlines.update', $current), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'is_renewed' => '1',
        ]);

        $this->assertEquals(2, Deadline::where('vehicle_id', $vehicle->id)->count());

        // Riapre in modifica la stessa scadenza (già rinnovata) e salva di
        // nuovo aggiungendo solo il km di riferimento, senza toccare nulla
        // che riguardi il rinnovo.
        $current->refresh();
        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $current), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'is_renewed' => '1',
            'last_mileage' => 95000,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(2, Deadline::where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('deadlines', [
            'id' => $current->id,
            'last_mileage' => 95000,
        ]);
    }

    public function test_renewal_still_skipped_when_a_next_occurrence_already_exists_even_if_the_transition_guard_is_bypassed(): void
    {
        // Scenario reale segnalato: un veicolo con più Revisioni Ministeriali
        // in catena (rinnovate in sequenza). Aggiungere solo il km alla PIÙ
        // VECCHIA delle rinnovate ricreava la scadenza che la rinnova già,
        // anche se esisteva già.
        //
        // Il controllo "solo al passaggio a rinnovata" (updateDeadline) da
        // solo non è sufficiente a garantirlo in ogni caso (es. dati storici
        // dove `is_renewed` risulta momentaneamente falso pur avendo già una
        // scadenza figlia): questo test forza quella condizione per
        // verificare che la guardia in createNextDeadlineAfterRenewal — non
        // creare se esiste già una scadenza con renews_deadline_id verso
        // questa — la copra comunque, indipendentemente dal motivo per cui
        // la prima guardia non ha bloccato la chiamata.
        $user = $this->createUser();
        $vehicle = $this->createVehicle(); // regular_inspection_months = 24
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $oldest = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'status' => 'pending',
            'due_date' => '2022-09-30',
            'is_renewed' => false,
        ]);

        // Primo rinnovo: crea normalmente la scadenza successiva (2024-09-30).
        $this->actingAs($user)->put(route('admin.deadlines.update', $oldest), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2022-09',
            'is_renewed' => '1',
        ]);
        $this->assertEquals(2, Deadline::where('vehicle_id', $vehicle->id)->count());
        $next = Deadline::where('renews_deadline_id', $oldest->id)->firstOrFail();

        // Forza is_renewed a falso direttamente sul record (bypassando il
        // form), per simulare qualunque causa renda inefficace il controllo
        // "appena passata a rinnovata" al salvataggio successivo.
        $oldest->forceFill(['is_renewed' => false])->save();

        $response = $this->actingAs($user)->put(route('admin.deadlines.update', $oldest), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2022-09',
            'is_renewed' => '1',
            'last_mileage' => 60000,
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals(2, Deadline::where('vehicle_id', $vehicle->id)->count());
        $this->assertDatabaseHas('deadlines', ['id' => $next->id, 'due_date' => '2024-09-30 00:00:00']);
    }

    // VALIDAZIONE DEI CAMPI OBBLIGATORI

}
