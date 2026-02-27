<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\Request;

class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $issues = Issue::with('vehicle')->get();
        return view('admin.issues.index', compact('issues'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()
            ->route('admin.issues.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return redirect()
            ->route('admin.issues.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Issue $issue)
    {
        return redirect()
            ->route('admin.issues.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Issue $issue)
    {
        return redirect()
            ->route('admin.issues.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Issue $issue)
    {
        return redirect()
            ->route('admin.issues.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Issue $issue)
    {
        return redirect()
            ->route('admin.issues.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }
}
