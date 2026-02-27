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
        return view('admin.providers.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:255',
                'contact_info' => 'nullable|string|max:255',
                'type' => 'nullable|string|max:255',
            ],
            [
                'name.required' => 'Il nome è obbligatorio.',
                'name.string' => 'Il nome deve essere una stringa.',
                'name.max' => 'Il nome non può superare i 255 caratteri.',
                'address.string' => 'L\'indirizzo deve essere una stringa.',
                'address.max' => 'L\'indirizzo non può superare i 255 caratteri.',
                'contact_info.string' => 'Le informazioni di contatto devono essere una stringa.',
                'contact_info.max' => 'Le informazioni di contatto non possono superare i 255 caratteri.',
                'type.string' => 'Il tipo deve essere una stringa.',
                'type.max' => 'Il tipo non può superare i 255 caratteri.',
            ]
        );

        $newProvider = new Provider();
        $newProvider->name = $data['name'];
        $newProvider->address = $data['address'] ?? null;
        $newProvider->contact_info = $data['contact_info'] ?? null;
        $newProvider->type = $data['type'] ?? null;
        $newProvider->save();

        return redirect()->route('admin.providers.show', $newProvider->id)->with('status', 'Struttura aggiunta con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Provider $provider)
    {
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
    public function update(Request $request, Provider $provider)
    {
        $data = $request->validate(
            [
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:255',
                'contact_info' => 'nullable|string|max:255',
                'type' => 'nullable|string|max:255',
            ],
            [
                'name.required' => 'Il nome è obbligatorio.',
                'name.string' => 'Il nome deve essere una stringa.',
                'name.max' => 'Il nome non può superare i 255 caratteri.',
                'address.string' => 'L\'indirizzo deve essere una stringa.',
                'address.max' => 'L\'indirizzo non può superare i 255 caratteri.',
                'contact_info.string' => 'Le informazioni di contatto devono essere una stringa.',
                'contact_info.max' => 'Le informazioni di contatto non possono superare i 255 caratteri.',
                'type.string' => 'Il tipo deve essere una stringa.',
                'type.max' => 'Il tipo non può superare i 255 caratteri.',
            ]
        );

        $provider->name = $data['name'];
        $provider->address = $data['address'] ?? null;
        $provider->contact_info = $data['contact_info'] ?? null;
        $provider->type = $data['type'] ?? null;
        $provider->update();

        return redirect()->route('admin.providers.show', $provider->id)->with('status', 'Struttura aggiornata con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Provider $provider)
    {
        $provider->delete();
        return redirect()->route('admin.providers.index')->with('status', 'Struttura eliminata con successo.');
    }
}
