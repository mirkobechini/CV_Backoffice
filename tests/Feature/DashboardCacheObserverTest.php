<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Provider;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\DashboardCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Audit funzionale 2026-11-25 (B2): EquipmentIssue/EquipmentMaintenanceRecord
 * non erano registrati su DashboardCacheObserver — crearli/modificarli non
 * invalidava mai la cache dashboard (5 minuti). Inoltre, per un'attrezzatura
 * non assegnata (visibile a tutti i gruppi) o un appuntamento ambiguo
 * (attrezzature di più gruppi diversi), invalidare solo il bucket "none"
 * lasciava ferme le dashboard dei gruppi reali che lo vedono.
 */
class DashboardCacheObserverTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name, string $inviteCode): Group
    {
        return Group::create(['name' => $name, 'invite_code' => $inviteCode]);
    }

    private function equipmentOnVehicleOf(?Group $group): Equipment
    {
        $type = EquipmentType::create(['name' => 'Estintore '.uniqid()]);

        if (! $group) {
            return Equipment::create(['equipment_type_id' => $type->id, 'name' => 'Estintore']);
        }

        $vehicleType = VehicleType::firstOrCreate(['name' => 'Ambulanza'], ['first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $vehicle = Vehicle::create([
            'license_plate' => 'AA'.random_int(100, 999).'AA',
            'internal_code' => (string) random_int(1000, 9999),
            'vehicle_type_id' => $vehicleType->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $group->id,
        ]);

        return Equipment::create(['equipment_type_id' => $type->id, 'vehicle_id' => $vehicle->id, 'name' => 'Estintore']);
    }

    public function test_creating_an_equipment_issue_on_an_unassigned_equipment_invalidates_every_groups_dashboard(): void
    {
        $groupA = $this->group('Gruppo A', 'AAAA1111');
        $groupB = $this->group('Gruppo B', 'BBBB2222');
        Cache::put(DashboardCache::key($groupA->id), ['stale' => true], 300);
        Cache::put(DashboardCache::key($groupB->id), ['stale' => true], 300);
        $equipment = $this->equipmentOnVehicleOf(null);

        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $this->assertFalse(Cache::has(DashboardCache::key($groupA->id)));
        $this->assertFalse(Cache::has(DashboardCache::key($groupB->id)));
    }

    public function test_creating_an_equipment_issue_on_assigned_equipment_invalidates_only_its_group(): void
    {
        $groupA = $this->group('Gruppo A', 'CCCC3333');
        $groupB = $this->group('Gruppo B', 'DDDD4444');
        Cache::put(DashboardCache::key($groupA->id), ['stale' => true], 300);
        Cache::put(DashboardCache::key($groupB->id), ['stale' => true], 300);
        $equipment = $this->equipmentOnVehicleOf($groupA);

        EquipmentIssue::create([
            'equipment_id' => $equipment->id,
            'description' => 'Manometro scarico',
            'status' => 'open',
        ]);

        $this->assertFalse(Cache::has(DashboardCache::key($groupA->id)));
        $this->assertTrue(Cache::has(DashboardCache::key($groupB->id)));
    }

    public function test_an_ambiguous_equipment_maintenance_record_invalidates_only_the_involved_groups(): void
    {
        $groupA = $this->group('Gruppo A', 'EEEE5555');
        $groupB = $this->group('Gruppo B', 'FFFF6666');
        $groupC = $this->group('Gruppo C', 'GGGG7777');
        $equipmentA = $this->equipmentOnVehicleOf($groupA);
        $equipmentB = $this->equipmentOnVehicleOf($groupB);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        $record = EquipmentMaintenanceRecord::create(['provider_id' => $provider->id, 'appointment_date' => today()]);
        $record->equipments()->attach([$equipmentA->id, $equipmentB->id]);

        // Popolata DOPO la creazione/collegamento: creare il record
        // invalida già la cache (nessuna attrezzatura ancora collegata a
        // quel punto, quindi il ramo "invalida tutti" scatta per
        // sicurezza) — qui si verifica il comportamento di un
        // aggiornamento successivo, con le attrezzature già collegate.
        Cache::put(DashboardCache::key($groupA->id), ['stale' => true], 300);
        Cache::put(DashboardCache::key($groupB->id), ['stale' => true], 300);
        Cache::put(DashboardCache::key($groupC->id), ['stale' => true], 300);

        // Ricarica: la relazione "equipments" potrebbe già essere cachata
        // (vuota o parziale) da un accesso precedente sulla stessa istanza.
        $record->fresh()->touch();

        $this->assertFalse(Cache::has(DashboardCache::key($groupA->id)));
        $this->assertFalse(Cache::has(DashboardCache::key($groupB->id)));
        $this->assertTrue(Cache::has(DashboardCache::key($groupC->id)));
    }
}
