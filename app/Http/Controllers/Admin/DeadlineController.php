<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class DeadlineController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $deadlines = Deadline::with('vehicle')->get();
        return view('admin.deadlines.index', compact('deadlines'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::all();
        return view('admin.deadlines.create', compact('vehicles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'type' => 'required|in:Assicurazione,Revisione Ministeriale,Revisione Impianto Ossigeno',
                'due_date' => 'required|date',
                'status' => 'required|in:renewed,pending,expired',
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'type.required' => 'La tipologia è obbligatoria.',
                'type.in' => 'La tipologia selezionata non è valida.',
                'due_date.required' => 'La data di scadenza è obbligatoria.',
                'due_date.date' => 'La data di scadenza deve essere una data valida.',
                'status.required' => 'Lo stato è obbligatorio.',
                'status.in' => 'Lo stato selezionato non è valido.',
            ]
        );

        $deadline = new Deadline();
        $deadline->vehicle_id = $data['vehicle_id'];
        $deadline->type = $data['type'];
        $deadline->due_date = $data['due_date'];
        $deadline->status = $data['status'];
        $deadline->save();

        return redirect()->route('admin.deadlines.show', $deadline)->with('success', 'Scadenza creata con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Deadline $deadline)
    {
        return view('admin.deadlines.show', compact('deadline'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Deadline $deadline)
    {
        $vehicles = Vehicle::all();
        return view('admin.deadlines.edit', compact('deadline', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Deadline $deadline)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'type' => 'required|in:Assicurazione,Revisione Ministeriale,Revisione Impianto Ossigeno',
                'due_date' => 'required|date',
                'status' => 'required|in:renewed,pending,expired',
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'type.required' => 'La tipologia è obbligatoria.',
                'type.in' => 'La tipologia selezionata non è valida.',
                'due_date.required' => 'La data di scadenza è obbligatoria.',
                'due_date.date' => 'La data di scadenza deve essere una data valida.',
                'status.required' => 'Lo stato è obbligatorio.',
                'status.in' => 'Lo stato selezionato non è valido.',
            ]
        );

        $deadline->vehicle_id = $data['vehicle_id'];
        $deadline->type = $data['type'];
        $deadline->due_date = $data['due_date'];
        $deadline->status = $data['status'];
        $deadline->update();

        return redirect()->route('admin.deadlines.show', $deadline)->with('success', 'Scadenza aggiornata con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Deadline $deadline)
    {
        $deadline->delete();
        return redirect()->route('admin.deadlines.index')->with('success', 'Scadenza eliminata con successo.');
    }
}
