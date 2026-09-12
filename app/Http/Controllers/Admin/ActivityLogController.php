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

        // Filtro per tipo di elemento (subject_type): il campo log_name è
        // sempre "default" per tutti i modelli (nessuno lo personalizza),
        // quindi non è utilizzabile come filtro; il tipo reale dell'elemento
        // coinvolto (veicolo, guasto, scadenza...) è invece su subject_type.
        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        // Filtro per descrizione (testo libero)
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        $activities = $query->paginate(50)->withQueryString();

        // Tipi di elemento disponibili per il filtro, con etichetta leggibile.
        $subjectTypes = Activity::whereNotNull('subject_type')
            ->distinct('subject_type')
            ->pluck('subject_type')
            ->values()
            ->mapWithKeys(fn($type) => [$type => $this->subjectTypeLabel($type)])
            ->sort();

        return view('admin.activity-log.index', compact('activities', 'subjectTypes'));
    }

    /**
     * Etichetta in italiano per un subject_type (classe modello).
     */
    public static function subjectTypeLabel(?string $subjectType): string
    {
        return match ($subjectType) {
            \App\Models\Vehicle::class => __('Veicolo'),
            \App\Models\Issue::class => __('Guasto'),
            \App\Models\Deadline::class => __('Scadenza'),
            \App\Models\MaintenanceRecord::class => __('Appuntamento'),
            \App\Models\Equipment::class => __('Attrezzatura'),
            \App\Models\EquipmentType::class => __('Tipo attrezzatura'),
            \App\Models\VehicleType::class => __('Tipo veicolo'),
            \App\Models\MileageLog::class => __('Chilometraggio'),
            \App\Models\Provider::class => __('Officina'),
            \App\Models\Group::class => __('Gruppo'),
            \App\Models\User::class => __('Utente'),
            default => $subjectType ? class_basename($subjectType) : __('N/D'),
        };
    }
}
