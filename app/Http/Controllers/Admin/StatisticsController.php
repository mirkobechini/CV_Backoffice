<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ChecksLicenseFeature;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\CostStatisticsService;
use App\Services\DeadlineStatisticsService;
use App\Services\EquipmentStatisticsService;
use App\Services\FleetUsageStatisticsService;
use App\Services\FuelStatisticsService;
use App\Services\IssueStatisticsService;
use App\Services\TireStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StatisticsController extends Controller
{
    use ChecksLicenseFeature;

    /**
     * Pagina statistiche (guasti/costi/affidabilità e utilizzo): riservata
     * a capi/sottocapi, non ai membri base (dati aggregati sull'intera
     * flotta del gruppo, non un'azione sul singolo veicolo come il resto
     * dell'app), e inclusa solo da una licenza Pro in su (vedi ADR §16).
     *
     * Ogni tab è un link server-side (?tab=...), non un toggle JS: il
     * controller calcola solo le statistiche della tab attiva, stesso
     * principio già usato per il filtro periodo su questa stessa pagina.
     */
    public function index(
        Request $request,
        IssueStatisticsService $issueStatistics,
        CostStatisticsService $costStatistics,
        FleetUsageStatisticsService $usageStatistics,
        FuelStatisticsService $fuelStatistics,
        TireStatisticsService $tireStatistics,
        EquipmentStatisticsService $equipmentStatistics,
        DeadlineStatisticsService $deadlineStatistics,
    ) {
        if (! $request->user()->canManageData()) {
            abort(403);
        }

        $this->abortUnlessLicenseHasFeature($request->user(), License::FEATURE_STATISTICS);

        $validated = $request->validate([
            'period' => 'nullable|in:all,12m,6m',
            'tab' => 'nullable|in:guasti,costi,affidabilita,carburante,pneumatici,attrezzature,scadenze',
        ]);
        $period = $validated['period'] ?? 'all';
        $tab = $validated['tab'] ?? 'guasti';

        $since = match ($period) {
            '12m' => Carbon::today()->subMonths(12),
            '6m' => Carbon::today()->subMonths(6),
            default => null,
        };

        $data = match ($tab) {
            'costi' => [
                'costByYear' => $costStatistics->costByYear($since),
                'costByVehicle' => $costStatistics->costByVehicle($since),
                'costByProvider' => $costStatistics->costByProvider($since),
                'costPerKm' => $costStatistics->costPerKm($since),
                'totalCostPerKm' => $costStatistics->totalCostPerKm($since),
                'costByVehicleType' => $costStatistics->costByVehicleType($since),
            ],
            'carburante' => [
                'litersAndCostByYear' => $fuelStatistics->litersAndCostByYear($since),
                'litersAndCostByVehicle' => $fuelStatistics->litersAndCostByVehicle($since),
                'litersAndCostByFuelType' => $fuelStatistics->litersAndCostByFuelType($since),
                'litersAndCostByVehicleType' => $fuelStatistics->litersAndCostByVehicleType($since),
                'averageConsumptionByVehicle' => $fuelStatistics->averageConsumptionByVehicle($since),
            ],
            'pneumatici' => [
                'tireCostByYear' => $tireStatistics->costByYear($since),
                'tireCostByVehicle' => $tireStatistics->costByVehicle($since),
                'tireChangeFrequencyByVehicle' => $tireStatistics->changeFrequencyByVehicle($since),
                'averageKmBetweenChangesByVehicle' => $tireStatistics->averageKmBetweenChangesByVehicle($since),
            ],
            'scadenze' => [
                'deadlineCountByStatus' => $deadlineStatistics->countByStatus(),
                'deadlineExpiredCountByType' => $deadlineStatistics->expiredCountByType(),
                'averageInsuranceRenewalCost' => $deadlineStatistics->averageInsuranceRenewalCost($since),
            ],
            'attrezzature' => [
                'equipmentIssueCountByEquipment' => $equipmentStatistics->issueCountByEquipment($since),
                'equipmentIssueCountByType' => $equipmentStatistics->issueCountByType($since),
                'equipmentCostByYear' => $equipmentStatistics->costByYear($since),
                'equipmentCostByEquipment' => $equipmentStatistics->costByEquipment($since),
            ],
            'affidabilita' => [
                // "Tutto" mostra il km assoluto (ultima lettura), non la
                // distanza percorsa da quando il veicolo è tracciato
                // nell'app — vedi FleetUsageStatisticsService::currentMileageByVehicle().
                'kmTraveledByVehicle' => $since === null
                    ? $usageStatistics->currentMileageByVehicle()
                    : $usageStatistics->kmTraveledByVehicle($since),
                'issueCountByVehicle' => $usageStatistics->issueCountByVehicle($since),
                'downtime' => $usageStatistics->averageDowntimeByProvider($since),
                'staleVehicles' => $usageStatistics->staleVehicles(),
                'fleetAgeByVehicle' => $usageStatistics->fleetAgeByVehicle(),
                'averageFleetAge' => $usageStatistics->averageFleetAge(),
            ],
            default => [
                'partCounts' => $issueStatistics->countsByPart($since),
                'vehiclePartMatrix' => $issueStatistics->vehiclePartMatrix($since),
                'monthlyTrend' => $issueStatistics->monthlyTrend($since),
                'costByPart' => $issueStatistics->costByPart($since),
                'countsByVehicleType' => $issueStatistics->countsByVehicleType($since),
            ],
        };

        return view('admin.statistics.index', ['period' => $period, 'tab' => $tab] + $data);
    }
}
