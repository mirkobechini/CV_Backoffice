<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCardsTest extends TestCase
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

    public function test_dashboard_shows_attention_banner_for_expired_deadline_and_stale_vehicle(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        // Nessun MileageLog/FuelLog registrato: il veicolo è "fermo" per
        // FleetUsageStatisticsService::staleVehicles() (restyle "Saru").
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => 'Revisione',
            'due_date' => now()->subDays(5),
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('2 cose richiedono attenzione oggi')
            ->assertSee('1 scadenza già scaduta')
            ->assertSee('1 veicolo fermo da tempo senza registrazioni');
    }

    public function test_dashboard_hides_attention_banner_when_nothing_needs_attention(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        MileageLog::create([
            'vehicle_id' => $vehicle->id,
            'log_date' => now(),
            'mileage' => 1000,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertDontSee('cose richiedono attenzione oggi');
    }

    public function test_dashboard_shows_which_vehicle_is_in_workshop_and_where(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Officina Rossi', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => now()->subDays(2),
            'return_date' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Veicoli in officina');
        $response->assertSee('0001');
        $response->assertSee('Officina Rossi');
    }

    public function test_dashboard_shows_future_appointment_only_in_upcoming_card(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Officina Rossi', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => now()->addDays(3),
            'return_date' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Prossimi appuntamenti');
        $response->assertDontSee('Veicoli in officina');
    }

    public function test_dashboard_hides_in_workshop_card_when_nothing_in_workshop(): void
    {
        $user = User::factory()->withRole('admin')->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Veicoli in officina');
    }

    public function test_dashboard_hides_tire_season_widget_when_no_tires_tracked(): void
    {
        $user = User::factory()->withRole('admin')->create();
        Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('Cambio gomme stagionale');
    }

    public function test_dashboard_shows_car_wash_card_with_washed_and_missing_vehicles(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $washed = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $missing = Vehicle::create([
            'license_plate' => 'EF456GH',
            'internal_code' => '0002',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Autolavaggio Test', 'type' => 'Lavaggio']);
        MaintenanceRecord::create([
            'vehicle_id' => $washed->id,
            'provider_id' => $provider->id,
            'activity_type' => MaintenanceRecord::ACTIVITY_WASH,
            'appointment_date' => now()->startOfMonth(),
            'return_date' => now()->startOfMonth(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Lavaggi del mese');
        $response->assertSee('0001');
        $response->assertSee('0002');
    }

    public function test_dashboard_car_wash_card_does_not_count_appointments_still_open(): void
    {
        // Un appuntamento di lavaggio non ancora concluso (return_date
        // nullo) non garantisce che il veicolo sia stato lavato davvero.
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Autolavaggio Test', 'type' => 'Lavaggio']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'activity_type' => MaintenanceRecord::ACTIVITY_WASH,
            'appointment_date' => now()->startOfMonth(),
            'return_date' => null,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('/ 1 veicoli lavati questo mese');
        $response->assertSee('1 veicolo da lavare');
    }

    public function test_dashboard_car_wash_card_ignores_washes_from_previous_month(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        $provider = Provider::create(['name' => 'Autolavaggio Test', 'type' => 'Lavaggio']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'activity_type' => MaintenanceRecord::ACTIVITY_WASH,
            'appointment_date' => now()->subMonth(),
            'return_date' => now()->subMonth(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('/ 1 veicoli lavati questo mese');
        $response->assertSee('1 veicolo da lavare');
    }

    public function test_dashboard_shows_total_premium_of_active_insurance_policies_only(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);

        $active = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_ASSICURAZIONE,
            'due_date' => now()->addMonths(3),
            'is_renewed' => false,
        ]);
        $active->insuranceCoverages()->create(['coverage_type' => 'RCA', 'cost' => 300]);
        $active->insuranceCoverages()->create(['coverage_type' => 'Kasko', 'cost' => 200]);

        // Già rinnovata: non deve entrare nel totale, altrimenti la stessa
        // polizza verrebbe contata due volte (quella vecchia + la nuova).
        $renewed = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_ASSICURAZIONE,
            'due_date' => now()->subMonths(10),
            'is_renewed' => true,
        ]);
        $renewed->insuranceCoverages()->create(['coverage_type' => 'RCA', 'cost' => 999]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('500,00');
        $response->assertDontSee('999,00');
    }
}
