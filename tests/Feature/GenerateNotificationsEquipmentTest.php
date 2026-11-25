<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifiche per guasti (EquipmentIssue) e appuntamenti (EquipmentMaintenanceRecord)
 * sulle attrezzature, estratte da GenerateNotificationsCommandTest per non
 * fargli superare la soglia dimensionale (era già vicino a 500 righe).
 */
class GenerateNotificationsEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(?Group $group = null): User
    {
        $user = User::factory()->create();
        ($group ?? $this->defaultGroup())->addUser($user, Group::ROLE_CAPO);

        return $user;
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(Group $group): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => $group->id,
        ]);
    }

    private function equipmentType(): EquipmentType
    {
        return EquipmentType::create(['name' => 'Estintore '.uniqid()]);
    }

    public function test_generates_notification_for_open_equipment_issue_on_unassigned_equipment(): void
    {
        $this->admin();
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_ISSUE,
            'title' => 'Guasto attrezzatura aperto: Estintore magazzino',
        ]);
    }

    public function test_does_not_duplicate_equipment_issue_notification(): void
    {
        $this->admin();
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications');
        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_generates_notification_for_upcoming_equipment_appointment(): void
    {
        $this->admin();
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        $provider = Provider::create(['name' => 'Officina Collaudi', 'type' => 'Centro Revisioni']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => Carbon::today()->addDays(3),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO,
        ]);
        $record->equipments()->attach($equipment->id);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_MAINTENANCE,
            'title' => 'Appuntamento attrezzature in arrivo: Estintore magazzino',
        ]);
    }

    public function test_does_not_notify_equipment_appointment_already_completed(): void
    {
        $this->admin();
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        $provider = Provider::create(['name' => 'Officina Collaudi', 'type' => 'Centro Revisioni']);
        $record = EquipmentMaintenanceRecord::create([
            'provider_id' => $provider->id,
            'appointment_date' => Carbon::today()->addDays(3),
            'return_date' => Carbon::today(),
            'activity_type' => EquipmentMaintenanceRecord::ACTIVITY_COLLAUDO,
        ]);
        $record->equipments()->attach($equipment->id);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_capo_is_not_notified_about_another_groups_equipment_issue(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $this->admin($groupA);

        $vehicleB = $this->vehicle($groupB);
        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'vehicle_id' => $vehicleB->id,
            'name' => 'Estintore cabina',
        ]);
        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_capo_is_notified_about_another_groups_unassigned_equipment_issue(): void
    {
        // Comportamento deliberato (stesso delle scadenze Equipment):
        // l'attrezzatura non assegnata a nessun veicolo non ha un gruppo
        // proprio, quindi resta visibile — e qui notificata — a tutti.
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'CCCC3333']);
        $this->admin($groupA);

        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_respects_notify_on_issue_toggle_for_equipment_issues(): void
    {
        $user = $this->admin();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'notify_on_issue', 'value' => '0']);

        $equipment = Equipment::create([
            'equipment_type_id' => $this->equipmentType()->id,
            'name' => 'Estintore magazzino',
        ]);
        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 0);
    }
}
