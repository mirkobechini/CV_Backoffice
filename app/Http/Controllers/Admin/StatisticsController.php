<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ChecksLicenseFeature;
use App\Http\Controllers\Controller;
use App\Models\License;
use App\Services\IssueStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StatisticsController extends Controller
{
    use ChecksLicenseFeature;

    /**
     * Pagina statistiche sui guasti per parte del veicolo: riservata a
     * capi/sottocapi, non ai membri base (dato aggregato sull'intera
     * flotta del gruppo, non un'azione sul singolo veicolo come il resto
     * dell'app), e inclusa solo da una licenza Pro in su (vedi ADR §16).
     */
    public function index(Request $request, IssueStatisticsService $statistics)
    {
        if (! $request->user()->canManageData()) {
            abort(403);
        }

        $this->abortUnlessLicenseHasFeature($request->user(), License::FEATURE_STATISTICS);

        $validated = $request->validate([
            'period' => 'nullable|in:all,12m,6m',
        ]);
        $period = $validated['period'] ?? 'all';

        $since = match ($period) {
            '12m' => Carbon::today()->subMonths(12),
            '6m' => Carbon::today()->subMonths(6),
            default => null,
        };

        $partCounts = $statistics->countsByPart($since);
        $vehiclePartMatrix = $statistics->vehiclePartMatrix($since);
        $monthlyTrend = $statistics->monthlyTrend($since);
        $costByPart = $statistics->costByPart($since);

        return view('admin.statistics.index', compact(
            'period',
            'partCounts',
            'vehiclePartMatrix',
            'monthlyTrend',
            'costByPart'
        ));
    }
}
