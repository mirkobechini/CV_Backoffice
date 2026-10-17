<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\ActivityLogController;
use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Issue;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
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

    private function vehicle(?Group $group = null, string $internalCode = '0001'): Vehicle
    {
        $brand = Brand::firstOrCreate(['name' => 'Fiat']);
        $model = CarModel::firstOrCreate(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::firstOrCreate(
            ['name' => 'Ambulanza'],
            ['first_inspection_months' => 48, 'regular_inspection_months' => 24]
        );

        return Vehicle::create([
            'license_plate' => 'AB'.$internalCode.'CD',
            'internal_code' => $internalCode,
            'vehicle_type_id' => $type->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => ($group ?? $this->defaultGroup())->id,
        ]);
    }

    public function test_activity_log_index_loads(): void
    {
        $user = $this->admin();
        activity()->causedBy($user)->log('Test activity');

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));
        $response->assertOk();
    }

    public function test_activity_log_filters_by_subject_type(): void
    {
        // log_name è sempre "default" per tutti i modelli: il filtro reale
        // per tipo di elemento si basa su subject_type, non su log_name
        // (bug corretto: il filtro precedente non aveva alcun effetto).
        $user = $this->admin();
        $vehicle = $this->vehicle($user->activeGroup());
        $issue = Issue::create(['vehicle_id' => $vehicle->id, 'description' => 'x', 'status' => 'open', 'event_date' => '2026-01-01']);

        Activity::create([
            'log_name' => 'default',
            'description' => 'Vehicle activity',
            'subject_type' => Vehicle::class,
            'subject_id' => $vehicle->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);
        Activity::create([
            'log_name' => 'default',
            'description' => 'Issue activity',
            'subject_type' => Issue::class,
            'subject_id' => $issue->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index', ['subject_type' => Vehicle::class]));

        $response->assertOk();
        $response->assertSee('Vehicle activity');
        $response->assertDontSee('Issue activity');
    }

    /**
     * spatie/laravel-activitylog v5 salva i valori prima/dopo (attributes/
     * old) in attribute_changes, non più in properties: la ricerca testuale
     * deve guardare anche lì, altrimenti cercare un valore che è cambiato
     * (es. un nome) non trova più nulla.
     */
    public function test_activity_log_search_matches_attribute_changes(): void
    {
        $user = $this->admin();
        $group = $user->activeGroup();
        $vehicleA = $this->vehicle($group, '0001');
        $vehicleB = $this->vehicle($group, '0002');

        // Le description sono il marcatore di "quale riga è stata
        // mostrata": il termine cercato non può servire a questo scopo,
        // dato che il campo di ricerca lo riflette sempre nel suo
        // attributo value, a prescindere dal filtro reale sui risultati.
        Activity::create([
            'log_name' => 'default',
            'description' => 'riga-con-match-marker',
            'subject_type' => Vehicle::class,
            'subject_id' => $vehicleA->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'attribute_changes' => ['attributes' => ['internal_code' => 'ZZ999FINDME']],
        ]);
        Activity::create([
            'log_name' => 'default',
            'description' => 'riga-senza-match-marker',
            'subject_type' => Vehicle::class,
            'subject_id' => $vehicleB->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
            'attribute_changes' => ['attributes' => ['internal_code' => 'UNRELATED']],
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index', ['q' => 'ZZ999FINDME']));

        $response->assertOk();
        $response->assertSee('riga-con-match-marker');
        $response->assertDontSee('riga-senza-match-marker');
    }

    public function test_activity_log_requires_auth(): void
    {
        $response = $this->get(route('admin.activity-log.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_property_rows_builds_before_after_diff_for_updates(): void
    {
        $properties = new Collection([
            'old' => ['status' => 'renewed', 'is_renewed' => true, 'updated_at' => '2026-09-12T15:52:21.000000Z'],
            'attributes' => ['status' => 'pending', 'is_renewed' => false, 'updated_at' => '2026-09-12T15:58:52.000000Z'],
        ]);

        $result = ActivityLogController::propertyRows($properties);

        $this->assertSame('diff', $result['mode']);
        // updated_at è un campo tecnico: va escluso dal confronto.
        $this->assertCount(2, $result['rows']);

        $statusRow = collect($result['rows'])->firstWhere('field', 'Stato');
        $this->assertSame('renewed', $statusRow['before']);
        $this->assertSame('pending', $statusRow['after']);

        $renewedRow = collect($result['rows'])->firstWhere('field', 'Rinnovata');
        $this->assertSame('Sì', $renewedRow['before']);
        $this->assertSame('No', $renewedRow['after']);
    }

    public function test_property_rows_shows_flat_values_for_creation(): void
    {
        $properties = new Collection([
            'attributes' => ['name' => 'Ducato', 'created_at' => '2026-09-12T15:52:21.000000Z'],
        ]);

        $result = ActivityLogController::propertyRows($properties);

        $this->assertSame('flat', $result['mode']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('Nome', $result['rows'][0]['field']);
        $this->assertSame('Ducato', $result['rows'][0]['after']);
    }

    public function test_property_rows_handles_empty_properties(): void
    {
        $result = ActivityLogController::propertyRows(new Collection);

        $this->assertSame('flat', $result['mode']);
        $this->assertSame([], $result['rows']);
    }

    // --- Isolamento per gruppo (bug segnalato 2026-10-17) ---

    public function test_vehicle_activity_from_another_group_is_hidden(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle($otherGroup);

        Activity::create([
            'log_name' => 'default',
            'description' => 'non-mio-marker',
            'subject_type' => Vehicle::class,
            'subject_id' => $otherVehicle->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $response->assertDontSee('non-mio-marker');
    }

    public function test_issue_activity_from_another_groups_vehicle_is_hidden(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle($otherGroup);
        $otherIssue = Issue::create(['vehicle_id' => $otherVehicle->id, 'description' => 'x', 'status' => 'open', 'event_date' => '2026-01-01']);

        Activity::create([
            'log_name' => 'default',
            'description' => 'guasto-non-mio-marker',
            'subject_type' => Issue::class,
            'subject_id' => $otherIssue->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $response->assertDontSee('guasto-non-mio-marker');
    }

    public function test_unassigned_equipment_activity_is_visible_to_all_groups(): void
    {
        // Attrezzatura non ancora assegnata a un veicolo non ha un gruppo
        // proprio: resta visibile a tutti, stesso criterio permissivo già
        // usato ovunque nell'app (ADR §10).
        $user = $this->admin();
        $equipmentType = EquipmentType::firstOrCreate(['name' => 'Defibrillatore']);
        $equipment = Equipment::create(['name' => 'Defibrillatore', 'equipment_type_id' => $equipmentType->id]);

        Activity::create([
            'log_name' => 'default',
            'description' => 'attrezzatura-non-assegnata-marker',
            'subject_type' => Equipment::class,
            'subject_id' => $equipment->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $response->assertSee('attrezzatura-non-assegnata-marker');
    }

    public function test_equipment_assigned_to_another_groups_vehicle_is_hidden(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle($otherGroup);
        $equipmentType = EquipmentType::firstOrCreate(['name' => 'Defibrillatore']);
        $equipment = Equipment::create(['name' => 'Defibrillatore', 'equipment_type_id' => $equipmentType->id, 'vehicle_id' => $otherVehicle->id]);

        Activity::create([
            'log_name' => 'default',
            'description' => 'attrezzatura-altro-gruppo-marker',
            'subject_type' => Equipment::class,
            'subject_id' => $equipment->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $response->assertDontSee('attrezzatura-altro-gruppo-marker');
    }

    public function test_shared_catalog_activity_is_visible_to_all_groups(): void
    {
        // Provider è un catalogo condiviso, nessun gruppo proprio: lo
        // stesso fornitore è visibile e modificabile da qualunque gruppo.
        $user = $this->admin();
        $provider = Provider::create(['name' => 'Officina Condivisa', 'type' => 'Meccanico']);

        Activity::create([
            'log_name' => 'default',
            'description' => 'fornitore-condiviso-marker',
            'subject_type' => Provider::class,
            'subject_id' => $provider->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $response->assertSee('fornitore-condiviso-marker');
    }

    public function test_user_without_active_group_sees_no_activity(): void
    {
        // Fail closed: nessun gruppo attivo deve mostrare nessuna
        // attività, mai tutte quelle esistenti.
        $user = User::factory()->create();
        $group = $this->defaultGroup();
        $vehicle = $this->vehicle($group);

        Activity::create([
            'log_name' => 'default',
            'description' => 'qualunque-attivita-marker',
            'subject_type' => Vehicle::class,
            'subject_id' => $vehicle->id,
            'causer_type' => User::class,
            'causer_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.activity-log.index'));

        $response->assertOk();
        $response->assertDontSee('qualunque-attivita-marker');
    }
}
