<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        $issues = Issue::with('vehicle')
            ->whereHas('vehicle', fn($q) => $q->forCurrentUser())
            ->when($request->q, fn($q, $search) => $q->search($search))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('event_date')
            ->paginate($request->per_page ?? 20);

        return response()->json($issues);
    }

    public function show(Request $request, Issue $issue)
    {
        // Il binding di rotta risolve per id senza filtro di gruppo: senza
        // questo controllo un token di un gruppo qualsiasi poteva leggere
        // il guasto di un altro gruppo indovinandone l'id (stessa logica di
        // Vehicle::scopeForCurrentUser()).
        $groupId = $request->user()->activeGroup()?->id;

        if ($groupId && $issue->vehicle?->group_id !== $groupId) {
            abort(404);
        }

        $issue->load('vehicle');
        return response()->json($issue);
    }

    /**
     * Suggerimenti descrizioni per autocomplete (tutti i veicoli).
     */
    public function suggestions(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);

        $results = Issue::selectRaw('description, COUNT(*) as total')
            ->where('description', 'like', '%' . $request->q . '%')
            ->groupBy('description')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json($results);
    }
}
