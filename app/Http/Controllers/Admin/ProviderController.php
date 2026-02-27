<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $providers = Provider::all();
        return view('admin.providers.index', compact('providers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()
            ->route('admin.providers.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return redirect()
            ->route('admin.providers.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Provider $provider)
    {
        return redirect()
            ->route('admin.providers.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Provider $provider)
    {
        return redirect()
            ->route('admin.providers.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Provider $provider)
    {
        return redirect()
            ->route('admin.providers.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provider $provider)
    {
        return redirect()
            ->route('admin.providers.index')
            ->with('status', 'Funzionalità non ancora disponibile.');
    }
}
