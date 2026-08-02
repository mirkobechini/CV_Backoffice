<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Display a listing of the activity log.
     */
    public function index(Request $request)
    {
        $query = Activity::with('causer')->latest();

        // Filtro per log_name (es. vehicle, issue, deadline...)
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        // Filtro per descrizione (testo libero)
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        $activities = $query->paginate(50);

        // Lista dei log_name disponibili per il filtro
        $logNames = Activity::distinct('log_name')->pluck('log_name')->filter()->values();

        return view('admin.activity-log.index', compact('activities', 'logNames'));
    }
}
