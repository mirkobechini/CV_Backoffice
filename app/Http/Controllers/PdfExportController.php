<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExportController extends Controller
{
    public function vehiclePdf(Vehicle $vehicle)
    {
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
