<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWorkshopAndRenewalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * il veicolo deve appartenervi per essere visibile all'utente di
     * questi test (le query della dashboard sono filtrate per gruppo).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    public function test_dashboard_shows_todays_appointment_only_in_workshop_card(): void
    {
        // Un appuntamento di oggi non ancora rientrato va SOLO in "Veicoli
        // in officina", mai anche in "Prossimi appuntamenti": prima le due
        // query si sovrapponevano su oggi (>= oggi / <= oggi), facendolo
        // comparire due volte (bug segnalato 2026-10-15).
        $this->travelTo(now()->setTime(23, 0));

        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Veicoli in officina');
        $response->assertSee('Nessun appuntamento imminente');
    }

    public function test_dashboard_does_not_show_vehicle_in_workshop_before_the_appointment_time(): void
    {
        // Appuntamento di oggi con orario non ancora raggiunto: il veicolo
        // non è ancora "in officina", resta tra i prossimi appuntamenti
        // (bug segnalato 2026-10-19: appuntamento delle 15 segnato in
        // officina già dalla mezzanotte).
        $this->travelTo(now()->setTime(10, 0));

        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today(),
            'appointment_time' => '15:00',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Veicoli in officina');
        $response->assertSee('Prossimi appuntamenti');
    }

    public function test_dashboard_shows_vehicle_in_workshop_once_the_appointment_time_has_passed(): void
    {
        $this->travelTo(now()->setTime(16, 0));

        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Officina Test', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => today(),
            'appointment_time' => '15:00',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Veicoli in officina');
        $response->assertSee('Nessun appuntamento imminente');
    }

    public function test_dashboard_reflects_deadline_renewal_immediately(): void
    {
        // Riproduce il bug: la dashboard è cachata 5 minuti per gruppo
        // (DashboardController) e, senza invalidazione esplicita, una
        // scadenza appena rinnovata restava visibile come scaduta finché la
        // cache non scadeva naturalmente.
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(10),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        // Prima visita: popola la cache dashboard con la scadenza ancora scaduta.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Scadenze scadute e non rinnovate')
            ->assertSee('Tagliando');

        $deadline->update(['status' => Deadline::STATUS_RENEWED, 'is_renewed' => true]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Nessuna scadenza scaduta');
        $response->assertDontSee('Tagliando');
    }

    public function test_dashboard_excludes_renewed_deadlines_from_upcoming(): void
    {
        // scopeUpcoming() filtrava solo per finestra di date, senza
        // escludere le scadenze già rinnovate: una scadenza rinnovata oggi
        // ma la cui vecchia due_date cadeva ancora entro i prossimi 30
        // giorni restava visibile tra le "imminenti" insieme alla nuova.
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_OXYGEN,
            'due_date' => now()->addDays(5),
            'status' => Deadline::STATUS_RENEWED,
            'is_renewed' => true,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_OXYGEN,
            'due_date' => now()->addYear(),
            'status' => Deadline::STATUS_VALID,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Nessuna scadenza imminente');
    }
}
