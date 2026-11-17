<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\CapsPerPage;
use App\Http\Controllers\Controller;
use App\Models\Deadline;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    use CapsPerPage;

    public function index(Request $request)
    {
        $deadlines = Deadline::with('vehicle')
            ->forCurrentUser()
            ->when($request->q, fn ($q, $search) => $q->search($search))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->orderBy('due_date')
            ->paginate($this->perPage($request));

        return response()->json($deadlines);
    }

    public function show(Request $request, Deadline $deadline)
    {
        // Vedi commento in Api\IssueController::show().
        $groupId = $request->user()->activeGroup()?->id;

        if (! $groupId || $deadline->vehicle?->group_id !== $groupId) {
            abort(404);
        }

        $deadline->load('vehicle');

        return response()->json($deadline);
    }
}
