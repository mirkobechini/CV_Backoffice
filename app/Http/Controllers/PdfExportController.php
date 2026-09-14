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
        ]);
        $pdf = Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true])
            ->loadView('pdfs.scheda-veicolo', compact('vehicle'));
        return $pdf->download('scheda-' . $vehicle->internal_code . '.pdf');
    }
}
