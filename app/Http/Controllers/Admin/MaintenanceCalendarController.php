<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

/**
 * Vista calendario degli appuntamenti e relativo feed JSON per FullCalendar.
 * Estratto da MaintenanceRecordController: non è un'azione CRUD sulla
 * risorsa, è una vista aggregata su più appuntamenti.
 */
class MaintenanceCalendarController extends Controller
{
    public function index()
    {
        return view('admin.maintenance-records.calendar');
    }

    public function events(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end' => 'required|date',
        ]);

        $records = MaintenanceRecord::with(['vehicle', 'provider'])
            ->whereBetween('appointment_date', [$request->start, $request->end])
            ->get();

        return response()->json(
            $records->map(function ($record) {
                $color = match ($record->activity_type) {
                    'Riparazione' => '#dc3545',
                    'Revisione Ministeriale' => '#0d6efd',
                    'Revisione Impianto Ossigeno' => '#6610f2',
                    'Tagliando' => '#198754',
                    'Cambio Gomme' => '#fd7e14',
                    'Lavaggio' => '#0dcaf0',
                    default => '#6c757d',
                };

                $title = $record->vehicle?->internal_code ?? 'N/A';
                if ($record->activity_type) {
                    $title .= ' - ' . $record->activity_type;
                }

                return [
                    'id' => $record->id,
                    'title' => $title,
                    'start' => $record->appointment_date?->toDateString(),
                    'end' => $record->return_date?->toDateString(),
                    'color' => $color,
                    'textColor' => '#fff',
                    'url' => route('admin.maintenance-records.show', $record->id),
                    'extendedProps' => [
                        'vehicle' => $record->vehicle?->internal_code,
                        'provider' => $record->provider?->name,
                        'activity_type' => $record->activity_type,
                    ],
                ];
            })
        );
    }
}
