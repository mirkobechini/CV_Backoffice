<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTireSeasonCardTest extends TestCase
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
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Cambio gomme stagionale');
        $response->assertSee('0001');
    }

    public function test_dashboard_tire_season_card_shows_next_switch_not_just_current_season(): void
    {
        // Regressione: la card diceva "gomme estive attese" in ottobre
        // (stagione corretta per oggi), letto da un capo come "sto
        // aspettando le estive" quando il prossimo cambio reale è a
        // invernali — fonte di confusione.
        $this->travelTo(now()->setDate(2026, 10, 15));

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
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Prossimo cambio: gomme invernali dal 15/11.');
    }

    public function test_dashboard_reflects_updated_tire_season_dates_immediately(): void
    {
        // Regressione: Group non era tra i modelli osservati da
        // DashboardCacheObserver, quindi cambiare le date di cambio gomme
        // non invalidava la cache dashboard — restavano visibili i valori
        // precedenti fino alla scadenza naturale della cache (5 minuti).
        $this->travelTo(now()->setDate(2026, 10, 15));

        $user = User::factory()->withRole('admin')->create();
        $group = $this->defaultGroup();
        $vehicle = Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'immatricolation_date' => now()->subYears(2),
            'group_id' => $group->id,
        ]);
        Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_SUMMER,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        // Scalda la cache dashboard con le date di default.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('Prossimo cambio: gomme invernali dal 15/11.');

        $group->update(['winter_switch_date' => '10-20']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Prossimo cambio: gomme invernali dal 20/10.');
    }

    public function test_dashboard_tire_season_card_warns_30_days_before_the_switch(): void
    {
        // Cambio invernale di default 15/11: il 20/10 siamo dentro i 30
        // giorni di preavviso. Un veicolo ancora con gomme estive deve
        // comparire come "da cambiare" già ora, non il giorno esatto del
        // cambio (segnalato da Mirko: vedeva sempre N/N fino all'ultimo).
        $this->travelTo(now()->setDate(2026, 10, 20));

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
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('1 veicolo da cambiare');
        $response->assertSee('0001');
        $response->assertDontSee(__('scaduto'));
    }

    public function test_dashboard_tire_season_card_shows_overdue_after_the_switch_date(): void
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
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('1 veicolo scaduto');
    }

    public function test_dashboard_tire_season_card_shows_compliant_outside_the_warning_window(): void
    {
        // A settembre, ben fuori dai 30 giorni di preavviso del cambio
        // invernale (15/11), un veicolo con gomme ancora estive è
        // correttamente in regola (N/N), nessun avviso prematuro.
        $this->travelTo(now()->setDate(2026, 9, 1));

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
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        // "da cambiare"/"scaduto" compaiono solo nel badge di conteggio dei
        // veicoli in attesa (es. "1 veicolo da cambiare"), non nel link
        // statico "Vedi pneumatici da cambiare" sempre presente in fondo.
        $response->assertDontSee('veicolo da cambiare');
        $response->assertDontSee('veicoli da cambiare');
        $response->assertDontSee(__('scaduto'));
    }
}
