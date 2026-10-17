<?php

namespace Tests\Feature\Crud;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Misure pneumatici consigliate (codice) e combinazioni ammesse per asse —
 * spezzato da VehicleCrudTest per restare sotto ~500 righe.
 */
class VehicleTireSizeTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function createVehicleDependencies(): array
    {
        $brand = Brand::create(['name' => 'Fiat']);
        $carModel = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $vehicleType = VehicleType::create([
            'name' => 'Ambulanza',
            'needs_oxygen_check' => true,
            'first_inspection_months' => 48,
            'regular_inspection_months' => 24,
        ]);

        return compact('brand', 'carModel', 'vehicleType');
    }

    private function createVehicle(): array
    {
        $dependencies = $this->createVehicleDependencies();
        $brand = $dependencies['brand'];
        $carModel = $dependencies['carModel'];
        $vehicleType = $dependencies['vehicleType'];

        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vehicleType->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $carModel->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $this->defaultGroup()->id,
        ]);

        return compact('brand', 'carModel', 'vehicleType', 'vehicle');
    }

    public function test_show_page_displays_allowed_tire_sizes_and_combinations(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle()['vehicle'];
        $vehicle->update([
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A2'],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.show', $vehicle));

        $response->assertOk();
        $response->assertSee('A1: 225/75R16C');
        $response->assertSee('A1-A2');
    }

    public function test_vehicle_store_saves_allowed_tire_sizes_with_code(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
        ]);

        $vehicle = Vehicle::first();
        $response->assertRedirect(route('admin.vehicles.show', $vehicle));
        $this->assertSame([
            ['size' => '225/75R16C', 'code' => 'A1'],
            ['size' => '205/55R16', 'code' => 'A2'],
        ], $vehicle->fresh()->allowed_tire_sizes);
    }

    public function test_vehicle_store_discards_tire_size_rows_without_a_size(): void
    {
        // Le righe rimosse/vuote del repeater inviano comunque un campo
        // nascosto vuoto: non devono fallire la validazione né finire nel
        // dato salvato. Una riga senza misura va scartata (non c'è nulla
        // da salvare); una riga CON misura ma senza codice va invece
        // mantenuta con un codice di fallback, vedi test dedicato sotto.
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
        ])->assertRedirect();

        $vehicle = Vehicle::first();
        $this->assertSame([['size' => '205/55R16', 'code' => 'A2']], $vehicle->fresh()->allowed_tire_sizes);
    }

    public function test_vehicle_store_assigns_fallback_code_to_tire_size_missing_one(): void
    {
        // Regressione: una misura con un valore reale non deve mai sparire
        // al salvataggio solo perché il codice non è compilato (bug
        // segnalato 2026-10-17 — un salvataggio che non toccava le misure
        // le cancellava silenziosamente). Riceve un codice sequenziale di
        // fallback, stesso criterio già usato dalla migrazione dati per lo
        // storico migrato da "position" a "code".
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => ''],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
        ])->assertRedirect();

        $vehicle = Vehicle::first();
        $this->assertSame([
            ['size' => '225/75R16C', 'code' => '1'],
            ['size' => '205/55R16', 'code' => 'A2'],
        ], $vehicle->fresh()->allowed_tire_sizes);
    }

    public function test_vehicle_store_saves_allowed_tire_size_combinations(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A2'],
                ['front_code' => 'A2', 'rear_code' => 'A2'],
            ],
        ])->assertRedirect();

        $vehicle = Vehicle::first();
        $this->assertSame([
            ['front_code' => 'A1', 'rear_code' => 'A2'],
            ['front_code' => 'A2', 'rear_code' => 'A2'],
        ], $vehicle->fresh()->allowed_tire_size_combinations);
    }

    public function test_vehicle_store_rejects_combination_referencing_unknown_code(): void
    {
        $user = $this->createUser();
        $data = $this->createVehicleDependencies();

        $response = $this->actingAs($user)->post(route('admin.vehicles.store'), [
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => '1234',
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A3'],
            ],
        ]);

        $response->assertSessionHasErrors(['allowed_tire_size_combinations.0.rear_code']);
        $this->assertDatabaseMissing('vehicles', ['license_plate' => 'AB123CD']);
    }

    public function test_adding_a_second_combination_preserves_the_existing_one(): void
    {
        // Regressione storica (#353): un indice collideva tra riga
        // esistente e riga aggiunta via JS. Qui si verifica solo il
        // comportamento del controller dato un array già correttamente
        // accoppiato per riga — l'accoppiamento vero e proprio lato HTML
        // è invece verificato in test_edit_page_renders_tire_size_and_combination_fields_with_matching_row_index().
        $user = $this->createUser();
        $data = $this->createVehicle();
        $vehicle = $data['vehicle'];
        $vehicle->update([
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A1'],
            ],
        ]);

        $this->actingAs($user)->put(route('admin.vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => $vehicle->internal_code,
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
            // Simula la riga esistente (sottomessa invariata) seguita da
            // una nuova riga appena aggiunta via JS.
            'allowed_tire_size_combinations' => [
                ['front_code' => 'A1', 'rear_code' => 'A1'],
                ['front_code' => 'A1', 'rear_code' => 'A2'],
            ],
        ])->assertRedirect(route('admin.vehicles.show', $vehicle));

        $this->assertSame([
            ['front_code' => 'A1', 'rear_code' => 'A1'],
            ['front_code' => 'A1', 'rear_code' => 'A2'],
        ], $vehicle->fresh()->allowed_tire_size_combinations);
    }

    public function test_updating_vehicle_without_touching_tire_sizes_does_not_wipe_a_size_missing_its_code(): void
    {
        // Riproduzione esatta del bug segnalato: un veicolo con una misura
        // già esistente ma senza codice (es. dato non ancora passato dalla
        // migrazione "position" -> "code") non deve perdere quella misura
        // con un salvataggio qualsiasi, anche uno che non tocca affatto la
        // sezione misure (qui: solo il nome del veicolo).
        $user = $this->createUser();
        $data = $this->createVehicle();
        $vehicle = $data['vehicle'];
        $vehicle->update(['allowed_tire_sizes' => [['size' => '225/75R16C', 'code' => null]]]);

        $this->actingAs($user)->put(route('admin.vehicles.update', $vehicle), [
            'license_plate' => $vehicle->license_plate,
            'vehicle_type_id' => $data['vehicleType']->id,
            'internal_code' => $vehicle->internal_code,
            'brand_id' => $data['brand']->id,
            'car_model_id' => $data['carModel']->id,
            'immatricolation_date' => '2024-01-01',
            'has_warranty_extension' => 0,
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => ''],
            ],
        ])->assertRedirect(route('admin.vehicles.show', $vehicle));

        $this->assertSame([
            ['size' => '225/75R16C', 'code' => '1'],
        ], $vehicle->fresh()->allowed_tire_sizes);
    }

    public function test_form_field_names_use_a_shared_explicit_index_per_row(): void
    {
        // Il bug reale (segnalato 2026-10-17): "size"/"code" (o
        // "front_code"/"rear_code") sono campi FRATELLI della stessa riga
        // ma con nomi "qualcosa[][sottocampo]" separati. Verificato con
        // parse_str() (lo stesso algoritmo usato da PHP per popolare
        // $_POST su una submission reale) che "[]" ripetuto su più campi
        // fratelli NON li accoppia per riga: ogni occorrenza incrementa un
        // contatore condiviso dell'intero array, a prescindere dal
        // sottocampo, spaiando size/code (e front_code/rear_code) tra loro.
        // Un indice esplicito condiviso (qui sotto, come deve fare la
        // view) è l'unico schema che accoppia correttamente i sottocampi.
        parse_str('a[][size]=S1&a[][code]=A1&a[][size]=S2&a[][code]=A2', $withAutoAppend);
        $this->assertNotSame(
            ['size' => 'S1', 'code' => 'A1'],
            $withAutoAppend['a'][0] ?? null,
            'con [] il sottocampo "code" non deve finire nella stessa riga di "size": è esattamente il bug.'
        );

        parse_str('a[0][size]=S1&a[0][code]=A1&a[1][size]=S2&a[1][code]=A2', $withExplicitIndex);
        $this->assertSame([
            0 => ['size' => 'S1', 'code' => 'A1'],
            1 => ['size' => 'S2', 'code' => 'A2'],
        ], $withExplicitIndex['a']);
    }

    public function test_edit_page_renders_tire_size_and_combination_fields_with_matching_row_index(): void
    {
        $user = $this->createUser();
        $vehicle = $this->createVehicle()['vehicle'];
        $vehicle->update([
            'allowed_tire_sizes' => [['size' => '225/75R16C', 'code' => 'A1']],
            'allowed_tire_size_combinations' => [['front_code' => 'A1', 'rear_code' => 'A2']],
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.edit', $vehicle));

        $response->assertOk();
        $response->assertSee('name="allowed_tire_sizes[0][size]"', false);
        $response->assertSee('name="allowed_tire_sizes[0][code]"', false);
        $response->assertSee('name="allowed_tire_size_combinations[0][front_code]"', false);
        $response->assertSee('name="allowed_tire_size_combinations[0][rear_code]"', false);
        // Il template clonato via JS per le righe aggiunte usa un
        // placeholder che lo script sostituisce con il prossimo indice
        // libero (qui 1, dato che esiste già la riga 0) — mai "[]".
        $response->assertSee('name="allowed_tire_sizes[__INDEX__][size]"', false);
        $response->assertSee('name="allowed_tire_size_combinations[__INDEX__][front_code]"', false);
        $response->assertSee('data-tire-size-next-index="1"', false);
        $response->assertSee('data-combo-next-index="1"', false);
    }

    public function test_combo_code_selects_offer_the_codes_entered_above_as_options(): void
    {
        // Richiesta UX (2026-10-17): le combinazioni ammesse devono essere
        // select con le sigle delle misure già inserite, non testo libero
        // da ricopiare a mano — evita anche di referenziare un codice
        // inesistente per errore di digitazione.
        $user = $this->createUser();
        $vehicle = $this->createVehicle()['vehicle'];
        $vehicle->update([
            'allowed_tire_sizes' => [
                ['size' => '225/75R16C', 'code' => 'A1'],
                ['size' => '205/55R16', 'code' => 'A2'],
            ],
            'allowed_tire_size_combinations' => [['front_code' => 'A1', 'rear_code' => 'A2']],
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.edit', $vehicle));

        $response->assertOk();
        $response->assertSee('class="input combo-code-select"', false);
        $response->assertSee('data-combo-selected="A1"', false);
        $response->assertSee('data-combo-selected="A2"', false);
        $response->assertDontSee('<input type="text" class="input" name="allowed_tire_size_combinations', false);
    }
}
