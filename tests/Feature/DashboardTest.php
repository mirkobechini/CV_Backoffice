<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
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

    public function test_dashboard_shows_deadline_expired_by_date_even_if_status_column_is_stale(): void
    {
        // La colonna status persistita viene risincronizzata solo alla
        // creazione/modifica della scadenza o visitando l'elenco scadenze
        // (Deadline::syncStatusesFromRules()): se il due_date passa senza
        // che nessuno tocchi quel record, status resta "pending" in DB
        // anche se la scadenza è ormai scaduta. La dashboard deve comunque
        // mostrarla, calcolando lo stato live invece di fidarsi di status.
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
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Scadenze scadute e non rinnovate');
        $response->assertSee('Tagliando');
    }

    public function test_dashboard_shows_deadline_expired_by_km_with_no_due_date(): void
    {
        // Scadenza "a km" (es. cinghia a secco): due_date è sempre null, lo
        // stato scaduto dipende solo dal chilometraggio attuale del veicolo
        // superiore a interval_km. Il pre-filtro SQL scopePossiblyExpired()
        // deve includerla comunque (non può escluderla senza interrogare il
        // km, quindi la lascia passare al filtro PHP esatto).
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
            'mileage' => 150000,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_CINGHIA,
            'due_date' => null,
            'interval_km' => 100000,
            'last_mileage' => 0,
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Scadenze scadute e non rinnovate');
        $response->assertSee('Cinghia Distribuzione');
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
