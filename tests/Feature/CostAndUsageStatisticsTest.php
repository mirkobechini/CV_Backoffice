<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\FuelLog;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\CostStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CostAndUsageStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(string $internalCode = '0001', ?Group $group = null, ?string $licensePlate = null): Vehicle
    {
        $brand = Brand::firstOrCreate(['name' => 'Fiat']);
        $model = CarModel::firstOrCreate(['name' => 'Ducato', 'brand_id' => $brand->id]);
        $type = VehicleType::firstOrCreate(
            ['name' => 'Ambulanza'],
            ['first_inspection_months' => 48, 'regular_inspection_months' => 24]
        );

        return Vehicle::create([
            'license_plate' => $licensePlate ?? 'AB'.$internalCode.'CD',
            'internal_code' => $internalCode,
            'vehicle_type_id' => $type->id,
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'immatricolation_date' => '2024-01-01',
            'group_id' => ($group ?? $this->defaultGroup())->id,
        ]);
    }

    private function provider(string $name = 'Officina Test'): Provider
    {
        return Provider::create(['name' => $name, 'type' => 'Meccanico']);
    }

    // --- Costi ---

    public function test_cost_by_year_sums_cost_grouped_by_appointment_year(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider();

        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2025-03-01', 'cost' => 100]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'cost' => 50]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-06-01', 'cost' => 30]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('100,00');
        $response->assertSee('80,00');
    }

    public function test_cost_by_vehicle_and_provider(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');
        $provider = $this->provider('Officina Alfa');

        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'cost' => 150]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('0001');
        $response->assertSee('Officina Alfa');
        $response->assertSeeInOrder(['Spesa per veicolo', '150,00']);
    }

    public function test_cost_statistics_exclude_another_groups_data(): void
    {
        $user = $this->admin();
        $otherGroup = Group::create(['name' => 'Altro gruppo', 'invite_code' => Group::generateInviteCode()]);
        $otherVehicle = $this->vehicle('9999', $otherGroup);
        $provider = $this->provider();

        MaintenanceRecord::create(['vehicle_id' => $otherVehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-01', 'cost' => 999]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertDontSee('999,00');
    }

    public function test_cost_per_km_excludes_vehicles_below_minimum_km(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider();

        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 1000]);
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-15', 'mileage' => 1000 + CostStatisticsService::MIN_KM_FOR_COST_PER_KM - 1]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-10', 'cost' => 100]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Nessun veicolo con dati sufficienti');
    }

    public function test_cost_per_km_includes_vehicles_above_minimum_km(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider();

        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 1000]);
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-15', 'mileage' => 1200]);
        MaintenanceRecord::create(['vehicle_id' => $vehicle->id, 'provider_id' => $provider->id, 'appointment_date' => '2026-01-10', 'cost' => 100]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'costi', 'period' => 'all']));

        $response->assertOk();
        // 100€ / 200km = 0,50 €/km
        $response->assertSee('0,50');
    }

    // --- Affidabilità/Utilizzo ---

    public function test_km_traveled_by_vehicle_needs_at_least_two_readings_in_period(): void
    {
        // Con un periodo specifico (non "Tutto") serve un intervallo:
        // una sola lettura non dà una distanza percorsa calcolabile.
        $user = $this->admin();
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => Carbon::today()->subMonths(1), 'mileage' => 1000]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => '6m']));

        $response->assertOk();
        $response->assertSee('Nessun veicolo con almeno due letture km');
    }

    public function test_km_traveled_by_vehicle_with_two_readings_in_period(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => Carbon::today()->subMonths(2), 'mileage' => 1000]);
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => Carbon::today()->subMonth(), 'mileage' => 1500]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => '6m']));

        $response->assertOk();
        $response->assertSee('500 km');
    }

    public function test_km_traveled_by_vehicle_shows_absolute_mileage_with_all_filter(): void
    {
        // Confermato con Mirko (2026-10-15): con "Tutto" deve comparire il
        // chilometraggio assoluto (ultima lettura), non la distanza
        // percorsa da quando il veicolo è tracciato nell'app — altrimenti
        // un mezzo che aveva già km prima di adottare l'app mostrerebbe
        // sempre meno del contachilometri reale.
        $user = $this->admin();
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2024-01-01', 'mileage' => 50000]);
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 61500]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('61.500 km');
        $response->assertDontSee('11.500 km');
    }

    public function test_km_traveled_by_vehicle_shows_absolute_mileage_with_single_reading(): void
    {
        // Con "Tutto" basta una sola lettura: non è una distanza (non serve
        // un intervallo), è il valore assoluto dell'ultima lettura.
        $user = $this->admin();
        $vehicle = $this->vehicle();
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => '2026-01-01', 'mileage' => 42000]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('42.000 km');
    }

    public function test_issue_count_by_vehicle_ranks_most_problematic_first(): void
    {
        $user = $this->admin();
        $vehicleA = $this->vehicle('0001');
        $vehicleB = $this->vehicle('0002');

        Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'a', 'status' => 'open', 'event_date' => '2026-01-01']);
        Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'b', 'status' => 'open', 'event_date' => '2026-01-02']);
        Issue::create(['vehicle_id' => $vehicleB->id, 'description' => 'c', 'status' => 'open', 'event_date' => '2026-01-03']);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['0001', '0002']);
    }

    public function test_average_downtime_by_provider_only_counts_completed_records(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle();
        $provider = $this->provider('Officina Beta');

        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-01-01',
            'return_date' => '2026-01-05',
        ]);
        // Non completato: non deve entrare nella media.
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => '2026-02-01',
        ]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Officina Beta');
        $response->assertSee('4 giorni');
    }

    /**
     * Regressione: internal_code non è univoco (solo il formato a 4 cifre
     * è vincolato). Due veicoli diversi con lo stesso codice (es. un
     * mezzo dismesso sostituito con lo stesso numero di flotta) non
     * devono confondere i rispettivi dati sotto un'unica riga — prima,
     * una mappa label => valore faceva sì che uno sovrascrivesse
     * silenziosamente l'altro (bug segnalato 2026-10-15).
     */
    public function test_km_traveled_keeps_vehicles_with_same_internal_code_separate(): void
    {
        $user = $this->admin();
        $oldVehicle = $this->vehicle('1726', licensePlate: 'OLD726XX');
        $currentVehicle = $this->vehicle('1726', licensePlate: 'NEW726XX');

        // Il vecchio mezzo (dismesso) ha un intervallo di km molto più
        // piccolo di quello attuale.
        MileageLog::create(['vehicle_id' => $oldVehicle->id, 'log_date' => '2024-01-01', 'mileage' => 20000]);
        MileageLog::create(['vehicle_id' => $oldVehicle->id, 'log_date' => '2024-06-01', 'mileage' => 21000]);

        MileageLog::create(['vehicle_id' => $currentVehicle->id, 'log_date' => '2026-01-01', 'mileage' => 61000]);
        MileageLog::create(['vehicle_id' => $currentVehicle->id, 'log_date' => '2026-02-01', 'mileage' => 82989]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        // Entrambi devono comparire con il proprio km assoluto (ultima
        // lettura), non uno che sovrascrive l'altro sotto l'etichetta
        // condivisa "1726".
        $response->assertSee('21.000 km');
        $response->assertSee('82.989 km');
    }

    // --- Veicoli fermi ---

    public function test_stale_vehicles_lists_vehicle_never_logged(): void
    {
        $user = $this->admin();
        $this->vehicle('0001');

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Veicoli fermi', '0001', 'Mai registrata']);
    }

    public function test_stale_vehicles_lists_vehicle_with_old_mileage_log(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => Carbon::today()->subDays(45), 'mileage' => 1000]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSeeInOrder(['Veicoli fermi', '0001', '45 giorni fa']);
    }

    public function test_stale_vehicles_excludes_vehicle_with_recent_activity(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => Carbon::today()->subDays(5), 'mileage' => 1000]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Tutti i veicoli hanno un chilometraggio o un rifornimento registrato');
    }

    public function test_stale_vehicles_uses_most_recent_of_mileage_or_fuel_log(): void
    {
        $user = $this->admin();
        $vehicle = $this->vehicle('0001');
        // Chilometraggio vecchio, ma rifornimento recente: il veicolo
        // non deve risultare fermo (si guarda l'attività più recente).
        MileageLog::create(['vehicle_id' => $vehicle->id, 'log_date' => Carbon::today()->subDays(60), 'mileage' => 1000]);
        FuelLog::create(['vehicle_id' => $vehicle->id, 'log_date' => Carbon::today()->subDays(2), 'liters' => 10]);

        $response = $this->actingAs($user)->get(route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => 'all']));

        $response->assertOk();
        $response->assertSee('Tutti i veicoli hanno un chilometraggio o un rifornimento registrato');
    }

    public function test_statistics_default_tab_is_guasti(): void
    {
        $user = $this->admin();

        $response = $this->actingAs($user)->get(route('admin.statistics.index'));

        $response->assertOk();
        $response->assertSee('Guasti per parte del veicolo');
    }
}
