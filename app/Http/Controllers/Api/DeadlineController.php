<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    public function index(Request $request)
    {
        $deadlines = Deadline::with('vehicle')
            ->when($request->q, fn($q, $search) => $q->search($search))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->orderBy('due_date')
            ->paginate($request->per_page ?? 20);

        return response()->json($deadlines);
    }

    public function show(Deadline $deadline)
    {
        $deadline->load('vehicle');
        return response()->json($deadline);
    }
}