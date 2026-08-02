<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    public function index(Request $request)
    {
        $issues = Issue::with('vehicle')
            ->when($request->q, fn($q, $search) => $q->search($search))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->orderByDesc('event_date')
            ->paginate($request->per_page ?? 20);

        return response()->json($issues);
    }

    public function show(Issue $issue)
    {
        $issue->load('vehicle');
        return response()->json($issue);
    }
}
