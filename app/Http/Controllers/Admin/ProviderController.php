<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\DetectsDuplicates;
use App\Http\Requests\StoreProviderRequest;
use App\Http\Requests\UpdateProviderRequest;
use App\Models\Provider;
use Illuminate\Support\Carbon;

class ProviderController extends Controller
{
    use DetectsDuplicates;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $providers = Provider::paginate(20);
        return view('admin.providers.index', compact('providers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.providers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProviderRequest $request)
    {
        $data = $request->validated();

        $duplicateProvider = $this->findDuplicate(Provider::class, [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'contact_info' => $data['contact_info'] ?? null,
            'type' => $data['type'] ?? null,
        ]);

        if ($duplicateProvider) {
            return redirect()
                ->route('admin.providers.show', $duplicateProvider->id)
                ->with('status', 'Struttura già registrata: creazione duplicata bloccata.');
        }

        $newProvider = Provider::create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'contact_info' => $data['contact_info'] ?? null,
            'type' => $data['type'] ?? null,
        ]);

        return redirect()->route('admin.providers.show', $newProvider->id)->with('status', 'Struttura aggiunta con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Provider $provider)
    {
        $provider->load('maintenanceRecords.items.itemable', 'maintenanceRecords.vehicle');

        return view('admin.providers.show', compact('provider'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Provider $provider)
    {
        return view('admin.providers.edit', compact('provider'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProviderRequest $request, Provider $provider)
    {
        $data = $request->validated();

        $provider->update([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'contact_info' => $data['contact_info'] ?? null,
            'type' => $data['type'] ?? null,
        ]);

        return redirect()->route('admin.providers.show', $provider->id)->with('status', 'Struttura aggiornata con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provider $provider)
    {
        $this->authorize('delete', $provider);
        $provider->delete();
        return redirect()->route('admin.providers.index')->with('status', 'Struttura eliminata con successo.');
    }
}
