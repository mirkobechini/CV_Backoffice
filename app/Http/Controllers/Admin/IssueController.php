<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class IssueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:vehicle,status,date',
            'sort_by' => 'nullable|in:vehicle,status,date',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $groupBy = $validated['group_by'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');

        $issues = Issue::with('vehicle')->get();

        $issues = $sortDir === 'desc'
            ? $issues->sortByDesc(function (Issue $issue) use ($sortBy) {
                return match ($sortBy) {
                    'vehicle' => $issue->vehicle?->internal_code ?? '',
                    'status' => $issue->status,
                    'date' => $issue->event_date?->format('Y-m-d') ?? '',
                };
            })->values()
            : $issues->sortBy(function (Issue $issue) use ($sortBy) {
                return match ($sortBy) {
                    'vehicle' => $issue->vehicle?->internal_code ?? '',
                    'status' => $issue->status,
                    'date' => $issue->event_date?->format('Y-m-d') ?? '',
                };
            })->values();

        $groupedIssues = null;
        if ($groupBy !== null) {
            $groupedIssues = $issues->groupBy(function (Issue $issue) use ($groupBy) {
                return match ($groupBy) {
                    'vehicle' => $issue->vehicle?->internal_code ?? 'N/A',
                    'status' => match ($issue->status) {
                        'open' => 'Aperto',
                        'in_progress' => 'In lavorazione',
                        'closed' => 'Risolto',
                        default => 'Sconosciuto',
                    },
                    'date' => $issue->event_date
                        ? ucfirst($issue->event_date->locale('it')->translatedFormat('F Y'))
                        : 'N/A',
                };
            });
        }

        return view('admin.issues.index', compact('issues', 'groupBy', 'sortBy', 'sortDir', 'groupedIssues'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::all();
        $selectedVehicleId = request('vehicle_id');

        return view('admin.issues.create', compact('vehicles', 'selectedVehicleId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'description' => 'required|string',
                'event_date' => 'required|date',
                'status' => 'required|in:open,in_progress,closed',
                'image' => 'nullable|image|max:2048', // Optional image upload
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'description.required' => 'La descrizione è obbligatoria.',
                'description.string' => 'La descrizione deve essere una stringa.',
                'event_date.required' => 'La data di segnalazione è obbligatoria.',
                'event_date.date' => 'La data di segnalazione deve essere una data valida.',
                'status.required' => 'Lo stato è obbligatorio.',
                'status.in' => 'Lo stato selezionato non è valido.',
                'image.image' => 'Il file caricato deve essere un\'immagine.',
                'image.max' => 'L\'immagine non può superare i 2MB.',
            ]
        );

        $duplicateIssue = Issue::query()
            ->where('vehicle_id', $data['vehicle_id'])
            ->where('description', $data['description'])
            ->whereDate('event_date', $data['event_date'])
            ->where('status', $data['status'])
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->latest('id')
            ->first();

        if ($duplicateIssue) {
            return redirect()
                ->route('admin.issues.show', $duplicateIssue->id)
                ->with('status', 'Guasto già registrato: creazione duplicata bloccata.');
        }

        $newIssue = new Issue();
        $newIssue->vehicle_id = $data['vehicle_id'];
        $newIssue->description = $data['description'];
        $newIssue->event_date = $data['event_date'];
        $newIssue->status = $data['status'];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('issue_images', 'public');
            $newIssue->photo = $path;
        }

        $newIssue->save();

        return redirect()->route('admin.issues.show', $newIssue->id)->with('status', 'Guasto aggiunto con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Issue $issue)
    {
        return view('admin.issues.show', compact('issue'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Issue $issue)
    {
        $vehicles = Vehicle::all();
        return view('admin.issues.edit', compact('issue', 'vehicles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Issue $issue)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'description' => 'required|string',
                'event_date' => 'required|date',
                'status' => 'required|in:open,in_progress,closed',
                'image' => 'nullable|image|max:2048', // Optional image upload
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'description.required' => 'La descrizione è obbligatoria.',
                'description.string' => 'La descrizione deve essere una stringa.',
                'event_date.required' => 'La data di segnalazione è obbligatoria.',
                'event_date.date' => 'La data di segnalazione deve essere una data valida.',
                'status.required' => 'Lo stato è obbligatorio.',
                'status.in' => 'Lo stato selezionato non è valido.',
                'image.image' => 'Il file caricato deve essere un\'immagine.',
                'image.max' => 'L\'immagine non può superare i 2MB.',
            ]
        );
        $issue->vehicle_id = $data['vehicle_id'];
        $issue->description = $data['description'];
        $issue->event_date = $data['event_date'];
        $issue->status = $data['status'];
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('issue_images', 'public');
            $issue->photo = $path;
        }
        $issue->update();
        return redirect()->route('admin.issues.show', $issue->id)->with('status', 'Guasto aggiornato con successo.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Issue $issue)
    {
        $issue->delete();
        return redirect()->route('admin.issues.index')->with('status', 'Guasto eliminato con successo.');
    }
}
