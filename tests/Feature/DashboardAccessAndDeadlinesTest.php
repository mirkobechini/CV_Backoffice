<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\MileageLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessAndDeadlinesTest extends TestCase
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
}
