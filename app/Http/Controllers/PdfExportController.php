<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExportController extends Controller
{
    public function vehiclePdf(Request $request, Vehicle $vehicle)
    {
        // Il binding di rotta risolve per id senza filtro di gruppo: senza
        // questo controllo chiunque autenticato poteva scaricare la scheda
        // PDF (guasti, scadenze, attrezzature, manutenzioni) di un veicolo
        // di un altro gruppo indovinandone l'id.
        $groupId = $request->user()->activeGroup()?->id;

        if ($groupId && $vehicle->group_id !== $groupId) {
            abort(404);
        }

        $vehicle->load([
            'brand',
            'carModel',
            'vehicleType',
            'issues',
            'deadlines' => function ($query) {
                $query->orderBy('due_date', 'desc');
            },
            'equipment.equipmentType',
            'maintenanceRecords.provider',
            'maintenanceRecords.items.itemable',
            'tires',
        ]);
        $pdf = Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true])
            ->loadView('pdfs.scheda-veicolo', compact('vehicle'));
        return $pdf->download('scheda-' . $vehicle->internal_code . '.pdf');
    }

    /**
     * Tabella riassuntiva dell'intera flotta: un veicolo per riga, con le
     * prossime scadenze (tagliando, revisioni, cinghia) e l'ultimo
     * chilometraggio registrato — per avere tutto a colpo d'occhio senza
     * aprire ogni scheda veicolo singolarmente.
     */
    public function fleetOverview(Request $request)
    {
        $vehicles = Vehicle::forCurrentUser()
            ->with(['brand', 'carModel', 'latestMileageLog'])
            ->with(['deadlines' => function ($query) {
                $query->where('is_renewed', false)->orderByDesc('due_date');
            }])
            ->orderBy('internal_code')
            ->get();

        $pdf = Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true])
            ->loadView('pdfs.fleet-overview', compact('vehicles'));

        return $pdf->download('flotta-' . now()->format('Y-m-d') . '.pdf');
    }
}
