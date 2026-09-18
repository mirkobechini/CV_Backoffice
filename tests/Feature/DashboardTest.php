<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_dashboard_requires_auth(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_expired_deadlines(): void
    {
        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(10),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Scadenze scadute e non rinnovate');
        $response->assertSee('Tagliando');
    }

    public function test_dashboard_shows_todays_appointment_later_in_the_day(): void
    {
        // appointment_date è una colonna date (mezzanotte); confrontarla con
        // now() (data+ora corrente) la escludeva dai "prossimi appuntamenti"
        // non appena l'ora corrente superava la mezzanotte — cioè sempre,
        // tranne nell'istante esatto in cui l'appuntamento veniva creato.
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
        $response->assertSee('Prossimi appuntamenti');
        $response->assertDontSee('Nessun appuntamento imminente');
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

    public function test_dashboard_shows_vehicle_pending_seasonal_tire_change(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 1));

        $user = User::factory()->withRole('admin')->create();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
        ]);
        Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_SUMMER,
            'quantity' => 4,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cambio gomme stagionale');
        $response->assertSee('0001');
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
}
