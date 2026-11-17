<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\CapsPerPage;
use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;

class MaintenanceRecordController extends Controller
{
    use CapsPerPage;

    public function index(Request $request)
    {
        $records = MaintenanceRecord::with(['vehicle', 'provider', 'items.itemable'])
            ->forCurrentUser()
            ->when($request->vehicle_id, fn ($q, $id) => $q->where('vehicle_id', $id))
            ->when($request->status, function ($q, $status) {
                if ($status === 'in_progress') {
                    $q->whereNull('return_date');
                } elseif ($status === 'completed') {
                    $q->whereNotNull('return_date');
                }
            })
            ->orderByDesc('appointment_date')
            ->paginate($this->perPage($request));

        return response()->json($records);
    }

    public function show(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        // Vedi commento in Api\IssueController::show().
        $groupId = $request->user()->activeGroup()?->id;

        if (! $groupId || $maintenanceRecord->vehicle?->group_id !== $groupId) {
            abort(404);
        }

        $maintenanceRecord->load(['vehicle', 'provider', 'items.itemable']);

        return response()->json($maintenanceRecord);
    }
}
