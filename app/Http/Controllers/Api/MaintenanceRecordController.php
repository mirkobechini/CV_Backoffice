<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

class MaintenanceRecordController extends Controller
{
    public function index(Request $request)
    {
        $records = MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable'])
            ->when($request->vehicle_id, fn($q, $id) => $q->where('vehicle_id', $id))
            ->when($request->status, function ($q, $status) {
                if ($status === 'in_progress') {
                    $q->whereNull('return_date');
                } elseif ($status === 'completed') {
                    $q->whereNotNull('return_date');
                }
            })
            ->orderByDesc('appointment_date')
            ->paginate($request->per_page ?? 20);

        return response()->json($records);
    }

    public function show(MaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->load(['vehicle', 'provider', 'items.itemable']);
        return response()->json($maintenanceRecord);
    }
}