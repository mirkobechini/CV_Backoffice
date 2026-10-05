<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrLabelTest extends TestCase
{
    use RefreshDatabase;

    private function group(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    public function test_vehicle_qr_label_downloads(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => $this->group()->id,
        ]);

        $response = $this->actingAs($user)->get(route('admin.vehicles.qr-label', $vehicle));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_vehicle_qr_label_rejects_other_groups_vehicle(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => $otherGroup->id,
        ]);

        $this->actingAs($user)->get(route('admin.vehicles.qr-label', $vehicle))->assertForbidden();
    }

    public function test_equipment_qr_label_downloads(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $type = EquipmentType::create(['name' => 'Estintore']);
        $equipment = Equipment::create([
            'equipment_type_id' => $type->id,
            'name' => 'Estintore 5kg',
            'serial_number' => 'SN-001',
        ]);

        $response = $this->actingAs($user)->get(route('admin.equipments.qr-label', $equipment));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }
}
