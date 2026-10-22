<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentStatusTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(string $plate, string $code, ?Group $group = null, array $overrides = []): Vehicle
    {
        $brand = Brand::create(['name' => 'Fiat '.$code]);
        $model = CarModel::create(['name' => 'Ducato '.$code, 'brand_id' => $brand->id]);
        $type = VehicleType::create(['name' => 'Ambulanza '.$code, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);

        return Vehicle::create(array_merge([
            'license_plate' => $plate,
            'internal_code' => $code,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'vehicle_type_id' => $type->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group?->id,
        ], $overrides));
    }

    public function test_page_is_reachable(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
    }

    public function test_shows_missing_badge_for_vehicle_without_registration_card(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee($vehicle->internal_code);
        $response->assertSee(__('Mancante'));
    }

    public function test_shows_present_badge_for_vehicle_with_registration_card(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $this->vehicle('AB123CD', '0001', $group, ['registration_card_path' => 'registration_cards/foo.pdf']);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee(__('Presente'));
    }

    public function test_shows_deadline_status_badge_for_each_type(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);
        Deadline::where('vehicle_id', $vehicle->id)->forceDelete();

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(10),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Scaduta');
    }

    public function test_excludes_another_groups_vehicles(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        $this->vehicle('AB123CD', '0001', $groupA);
        $vehicleB = $this->vehicle('EF456GH', '0002', $groupB);

        $response = $this->actingAs($userA)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertDontSee($vehicleB->internal_code);
    }

    public function test_shows_equipment_status_badges(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);
        $equipmentType = EquipmentType::create(['name' => 'Estintore']);
        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore cabina',
            'expiration_date' => now()->subDays(5),
        ]);

        $response = $this->actingAs($user)->get(route('admin.documents.index'));

        $response->assertOk();
        $response->assertSee('Estintore cabina');
        $response->assertSee('Scaduta');
    }

    public function test_equipment_status_filter_excludes_non_matching_items(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);
        $equipmentType = EquipmentType::create(['name' => 'Estintore']);
        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore scaduto',
            'expiration_date' => now()->subDays(5),
        ]);
        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $equipmentType->id,
            'name' => 'Estintore valido',
            'expiration_date' => now()->addYear(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.documents.index', ['equipment_status_filter' => 'expired']));

        $response->assertOk();
        $response->assertSee('Estintore scaduto');
        $response->assertDontSee('Estintore valido');
    }

    public function test_equipment_can_be_grouped_by_type(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);
        $extinguisherType = EquipmentType::create(['name' => 'Estintore']);
        $chairType = EquipmentType::create(['name' => 'Sedia']);
        Equipment::create(['vehicle_id' => $vehicle->id, 'equipment_type_id' => $extinguisherType->id, 'name' => 'Estintore cabina']);
        Equipment::create(['vehicle_id' => $vehicle->id, 'equipment_type_id' => $chairType->id, 'name' => 'Sedia principale']);

        $response = $this->actingAs($user)->get(route('admin.documents.index', ['equipment_group_by' => 'type']));

        $response->assertOk();
        $response->assertSeeInOrder(['Estintore', 'Estintore cabina', 'Sedia', 'Sedia principale']);
    }

    public function test_export_pdf_is_downloadable(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $this->vehicle('AB123CD', '0001', $group);

        $response = $this->actingAs($user)->get(route('admin.documents.export-pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_export_csv_flags_expired_deadline_and_workshop_status(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);

        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(10),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('admin.documents.export-csv'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('0001', $content);
        $this->assertStringContainsString('In officina', $content);
        $this->assertStringContainsString('Non in regola', $content);
        $this->assertStringContainsString(Deadline::TYPE_TAGLIANDO, $content);
    }

    public function test_export_csv_shows_available_and_compliant_vehicle(): void
    {
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);

        // Audit 2026-10-22 (B3): "in regola" richiede ora un record non
        // scaduto per OGNUNA delle scadenze obbligatorie, non più la sola
        // assenza di scadenze scadute.
        foreach ([Deadline::TYPE_ASSICURAZIONE, Deadline::TYPE_MINISTERIAL, Deadline::TYPE_TAGLIANDO] as $type) {
            Deadline::create([
                'vehicle_id' => $vehicle->id,
                'type' => $type,
                'due_date' => now()->addMonths(6),
                'status' => Deadline::STATUS_PENDING,
                'is_renewed' => false,
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.documents.export-csv'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Disponibile', $content);
        $this->assertStringContainsString('In regola', $content);
    }

    public function test_export_csv_flags_vehicle_with_no_deadline_record_as_non_compliant(): void
    {
        // Audit 2026-10-22 (B3): un veicolo a cui manca del tutto una
        // scadenza obbligatoria (nessun record, non solo una scaduta)
        // risultava "In regola" per assenza di dati.
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $this->vehicle('AB123CD', '0001', $group);

        $response = $this->actingAs($user)->get(route('admin.documents.export-csv'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('Non in regola', $content);
        $this->assertStringContainsString(Deadline::TYPE_ASSICURAZIONE, $content);
    }

    public function test_export_csv_does_not_require_oxygen_or_timing_belt_when_not_applicable(): void
    {
        // Revisione Ossigeno e Cinghia non si applicano a tutti i veicoli
        // (vedi needsTimingBeltDeadline()/supportsOxygenCheckForVehicle()):
        // la loro assenza non deve rendere "non in regola" un veicolo per
        // cui semplicemente non si applicano.
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $user = User::factory()->create();
        $group->addUser($user, Group::ROLE_CAPO);
        $vehicle = $this->vehicle('AB123CD', '0001', $group);

        foreach ([Deadline::TYPE_ASSICURAZIONE, Deadline::TYPE_MINISTERIAL, Deadline::TYPE_TAGLIANDO] as $type) {
            Deadline::create([
                'vehicle_id' => $vehicle->id,
                'type' => $type,
                'due_date' => now()->addMonths(6),
                'status' => Deadline::STATUS_PENDING,
                'is_renewed' => false,
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.documents.export-csv'));

        $response->assertOk();
        $this->assertStringContainsString('In regola', $response->streamedContent());
    }
}
