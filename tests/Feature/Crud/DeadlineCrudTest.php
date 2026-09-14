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

class DeadlineCrudTest extends TestCase
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
        return (compact("vehicle", 'deadline'));
    }

    public function test_deadline_index_page_is_reachable(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get(route('admin.deadlines.index'));

        $response->assertStatus(200);
    }


    public function test_deadline_create_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user

        $response = $this->actingAs($user)->get(route('admin.deadlines.create'));

        $response->assertStatus(200);
    }



    public function test_deadline_show_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->get(route('admin.deadlines.show', $deadline));

        $response->assertStatus(200);
    }

    public function test_deadline_edit_page_is_reachable(): void
    {
        $user = $this->createUser();    //fake user
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->get(route('admin.deadlines.edit', $deadline));

        $response->assertStatus(200);
    }


    public function test_deadline_can_be_stored(): void
    {
        $user = $this->createUser();    //fake user
        $vehicle = $this->createVehicle();


        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => "2025-01",
        ]);

        $deadline = Deadline::latest('id')->first();

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
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
            'type' => 'Assicurazione',
            'status' => 'pending',
            'due_date' => '2025-01',
        ]);

        $response->assertRedirect(route('admin.deadlines.show', $deadline));
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
        ]);
    }

    public function test_deadline_can_be_deleted(): void
    {
        $user = $this->createUser();

        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->delete(route('admin.deadlines.destroy', $deadline));

        $response->assertRedirect(route('admin.deadlines.index'));
        $this->assertSoftDeleted($deadline);
    }

    public function test_deleting_from_show_page_redirects_to_index_not_404(): void
    {
        // Il modale di conferma imposta "back" sull'URL della pagina
        // corrente: eliminando dalla show, "back" punterebbe alla scadenza
        // appena cancellata (404) se seguito alla lettera.
        $user = $this->createUser();
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->delete(route('admin.deadlines.destroy', $deadline), [
            'back' => route('admin.deadlines.show', $deadline),
        ]);

        $response->assertRedirect(route('admin.deadlines.index'));
        $this->assertSoftDeleted($deadline);
    }

    public function test_deleting_respects_back_when_it_is_not_the_deleted_show_page(): void
    {
        $user = $this->createUser();
        $deadline = $this->createDeadline()['deadline'];

        $response = $this->actingAs($user)->delete(route('admin.deadlines.destroy', $deadline), [
            'back' => route('admin.deadlines.index') . '?status_filter=expired',
        ]);

        $response->assertRedirect(route('admin.deadlines.index') . '?status_filter=expired');
    }

    public function test_ministerial_due_date_can_be_set_manually_instead_of_auto_calculated(): void
    {
        // Prima del fix, per i tipi a calcolo automatico il campo due_date
        // veniva ignorato: qualunque valore inviato non aveva alcun effetto.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2030-06',
        ]);

        $deadline = Deadline::latest('id')->first();

        $response->assertSessionDoesntHaveErrors();
        $this->assertEquals('2030-06-30', $deadline->due_date->toDateString());
    }

    public function test_creating_new_ministerial_revision_auto_renews_previous_one(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        // createVehicle() fa scattare VehicleObserver, che genera già in
        // automatico delle scadenze (inclusa una Revisione Ministeriale
        // futura): le rimuoviamo per partire da uno stato pulito e
        // controllato in questo test.
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'status' => 'expired',
            'due_date' => '2020-06-30',
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2030-06',
        ]);

        $newDeadline = Deadline::latest('id')->first();

        $response->assertSessionDoesntHaveErrors();
        $this->assertNotEquals($current->id, $newDeadline->id);
        $this->assertEquals($current->id, $newDeadline->renews_deadline_id);
        $this->assertDatabaseHas('deadlines', [
            'id' => $current->id,
            'is_renewed' => true,
            'status' => 'renewed',
        ]);
    }

    public function test_deleting_auto_renewing_deadline_reverts_previous_to_expired(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        // Vedi commento in test_creating_new_ministerial_revision_auto_renews_previous_one.
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        $current = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'status' => 'expired',
            'due_date' => '2020-06-30',
            'is_renewed' => false,
        ]);

        $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'due_date' => '2030-06',
        ]);
        $newDeadline = Deadline::latest('id')->first();

        $response = $this->actingAs($user)->delete(route('admin.deadlines.destroy', $newDeadline));

        $response->assertRedirect(route('admin.deadlines.index'));
        $this->assertSoftDeleted($newDeadline);
        $this->assertDatabaseHas('deadlines', [
            'id' => $current->id,
            'is_renewed' => false,
            'status' => 'expired',
        ]);
    }

    public function test_ministerial_revision_can_optionally_record_mileage(): void
    {
        // Per le revisioni (data auto-calcolata) il km è solo un'annotazione
        // facoltativa: non deve essere richiesto né bloccare il salvataggio.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        $response = $this->actingAs($user)->post(route('admin.deadlines.store'), [
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione Ministeriale',
            'status' => 'renewed',
            'last_mileage' => 87000,
        ]);

        $deadline = Deadline::latest('id')->first();

        $response->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('deadlines', [
            'id' => $deadline->id,
            'type' => 'Revisione Ministeriale',
            'last_mileage' => 87000,
            'interval_km' => null,
        ]);
    }

    public function test_index_shows_all_types_by_default_not_only_revisions(): void
    {
        // Il filtro "ultima revisione per veicolo" (attivo di default) deve
        // solo deduplicare per veicolo+tipo, non nascondere del tutto i tipi
        // diversi da ministeriale/ossigeno.
        $user = $this->createUser();
        $vehicle = $this->createVehicle();

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Assicurazione',
            'status' => 'renewed',
            'due_date' => '2025-06',
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Tagliando',
            'status' => 'renewed',
            'due_date' => '2025-07',
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Cinghia Distribuzione',
            'status' => 'renewed',
            'due_date' => '2025-08',
        ]);

        $response = $this->actingAs($user)->get(route('admin.deadlines.index'));

        $response->assertOk();
        $response->assertSee('Assicurazione');
        $response->assertSee('Tagliando');
        $response->assertSee('Cinghia Distribuzione');
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
    }
}
