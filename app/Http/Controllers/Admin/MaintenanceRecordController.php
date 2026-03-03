<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Provider;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaintenanceRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'group_by' => 'nullable|in:vehicle,description,date',
            'sort_by' => 'nullable|in:vehicle,description,date',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $groupBy = $validated['group_by'] ?? null;
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? ($validated['sort_by'] ?? null ? 'asc' : 'desc');

        $maintenanceRecords = MaintenanceRecord::with(['vehicle', 'provider', 'issue'])->get();

        $maintenanceRecords = $sortDir === 'desc'
            ? $maintenanceRecords->sortByDesc(function (MaintenanceRecord $record) use ($sortBy) {
                return match ($sortBy) {
                    'vehicle' => $record->vehicle?->internal_code ?? '',
                    'description' => $record->issue?->description ?? ($record->activity_type ?? ''),
                    'date' => $record->appointment_date?->format('Y-m-d') ?? '',
                };
            })->values()
            : $maintenanceRecords->sortBy(function (MaintenanceRecord $record) use ($sortBy) {
                return match ($sortBy) {
                    'vehicle' => $record->vehicle?->internal_code ?? '',
                    'description' => $record->issue?->description ?? ($record->activity_type ?? ''),
                    'date' => $record->appointment_date?->format('Y-m-d') ?? '',
                };
            })->values();

        $groupedMaintenanceRecords = null;
        if ($groupBy !== null) {
            $groupedMaintenanceRecords = $maintenanceRecords->groupBy(function (MaintenanceRecord $record) use ($groupBy) {
                return match ($groupBy) {
                    'vehicle' => $record->vehicle?->internal_code ?? 'N/A',
                    'description' => $record->issue?->description ?? ($record->activity_type ?? 'N/A'),
                    'date' => $record->appointment_date
                        ? ucfirst($record->appointment_date->locale('it')->translatedFormat('F Y'))
                        : 'N/A',
                };
            });
        }

        return view('admin.maintenancerecords.index', compact('maintenanceRecords', 'groupBy', 'sortBy', 'sortDir', 'groupedMaintenanceRecords'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $vehicles = Vehicle::all();
        $providers = Provider::all();
        $openIssues = Issue::where('status', 'open')->get(['id', 'vehicle_id', 'description']);
        return view('admin.maintenancerecords.create', compact('vehicles', 'providers', 'openIssues'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'issue_id' => 'required|exists:issues,id',
                'provider_id' => 'required|exists:providers,id',
                'appointment_date' => 'required|date',
                'return_date' => 'nullable|date|after_or_equal:appointment_date',
                'activity_type' => ['nullable', 'string', 'max:255', Rule::in(MaintenanceRecord::ACTIVITY_TYPES)],
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'issue_id.required' => 'Il guasto è obbligatorio.',
                'issue_id.exists' => 'Il guasto selezionato non esiste.',
                'provider_id.required' => 'L\'officina è obbligatoria.',
                'provider_id.exists' => 'L\'officina selezionata non esiste.',
                'appointment_date.required' => 'La data dell\'appuntamento è obbligatoria.',
                'appointment_date.date' => 'La data dell\'appuntamento deve essere una data valida.',
                'return_date.date' => 'La data di completamento deve essere una data valida.',
                'return_date.after_or_equal' => 'La data di completamento deve essere uguale o successiva alla data dell\'appuntamento.',
                'activity_type.string' => 'Il tipo di attività deve essere una stringa.',
                'activity_type.max' => 'Il tipo di attività non può superare i 255 caratteri.',
                'activity_type.in' => 'Il tipo di attività selezionato non è valido.',
            ]
        );

        $issueBelongsToVehicle = Issue::where('id', $data['issue_id'])
            ->where('vehicle_id', $data['vehicle_id'])
            ->exists();

        if (!$issueBelongsToVehicle) {
            return back()
                ->withErrors(['issue_id' => 'Il guasto selezionato non appartiene al veicolo scelto.'])
                ->withInput();
        }

        $duplicateRecord = MaintenanceRecord::query()
            ->where('vehicle_id', $data['vehicle_id'])
            ->where('issue_id', $data['issue_id'])
            ->where('provider_id', $data['provider_id'])
            ->whereDate('appointment_date', $data['appointment_date'])
            ->where(function ($query) use ($data) {
                if (array_key_exists('return_date', $data) && $data['return_date'] !== null) {
                    $query->whereDate('return_date', $data['return_date']);
                } else {
                    $query->whereNull('return_date');
                }
            })
            ->where(function ($query) use ($data) {
                if (array_key_exists('activity_type', $data) && $data['activity_type'] !== null) {
                    $query->where('activity_type', $data['activity_type']);
                } else {
                    $query->whereNull('activity_type');
                }
            })
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->latest('id')
            ->first();

        if ($duplicateRecord) {
            return redirect()
                ->route('admin.maintenancerecords.show', $duplicateRecord->id)
                ->with('status', 'Intervento già registrato: creazione duplicata bloccata.');
        }

        $newRecord = new MaintenanceRecord();
        $newRecord->vehicle_id = $data['vehicle_id'];
        $newRecord->issue_id = $data['issue_id'];
        $newRecord->provider_id = $data['provider_id'];
        $newRecord->appointment_date = $data['appointment_date'];
        $newRecord->return_date = $data['return_date'] ?? null;
        $newRecord->activity_type = $data['activity_type'] ?? null;
        $newRecord->save();

        return redirect()->route('admin.maintenancerecords.show', $newRecord->id)->with('status', 'Intervento aggiunto con successo.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->load(['vehicle', 'provider', 'issue']);
        return view('admin.maintenancerecords.show', compact('maintenanceRecord'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->load(['vehicle', 'provider', 'issue']);

        $vehicles = Vehicle::all();
        $providers = Provider::all();
        $openIssues = Issue::where('status', 'open')
            ->orWhere('id', $maintenanceRecord->issue_id)
            ->get(['id', 'vehicle_id', 'description', 'status']);

        return view('admin.maintenancerecords.edit', compact('maintenanceRecord', 'vehicles', 'providers', 'openIssues'));
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        $data = $request->validate(
            [
                'vehicle_id' => 'required|exists:vehicles,id',
                'issue_id' => 'required|exists:issues,id',
                'provider_id' => 'required|exists:providers,id',
                'appointment_date' => 'required|date',
                'return_date' => 'nullable|date|after_or_equal:appointment_date',
                'activity_type' => ['nullable', 'string', 'max:255', Rule::in(MaintenanceRecord::ACTIVITY_TYPES)],
                'issue_resolved' => 'nullable|boolean',
            ],
            [
                'vehicle_id.required' => 'Il veicolo è obbligatorio.',
                'vehicle_id.exists' => 'Il veicolo selezionato non esiste.',
                'issue_id.required' => 'Il guasto è obbligatorio.',
                'issue_id.exists' => 'Il guasto selezionato non esiste.',
                'provider_id.required' => 'L\'officina è obbligatoria.',
                'provider_id.exists' => 'L\'officina selezionata non esiste.',
                'appointment_date.required' => 'La data dell\'appuntamento è obbligatoria.',
                'appointment_date.date' => 'La data dell\'appuntamento deve essere una data valida.',
                'return_date.date' => 'La data di completamento deve essere una data valida.',
                'return_date.after_or_equal' => 'La data di completamento deve essere uguale o successiva alla data dell\'appuntamento.',
                'activity_type.string' => 'Il tipo di attività deve essere una stringa.',
                'activity_type.max' => 'Il tipo di attività non può superare i 255 caratteri.',
                'activity_type.in' => 'Il tipo di attività selezionato non è valido.',
                'issue_resolved.boolean' => 'Il valore selezionato per la risoluzione del guasto non è valido.',
            ]
        );

        $issueBelongsToVehicle = Issue::where('id', $data['issue_id'])
            ->where('vehicle_id', $data['vehicle_id'])
            ->exists();

        if (!$issueBelongsToVehicle) {
            return back()
                ->withErrors(['issue_id' => 'Il guasto selezionato non appartiene al veicolo scelto.'])
                ->withInput();
        }

        $maintenanceRecord->vehicle_id = $data['vehicle_id'];
        $maintenanceRecord->issue_id = $data['issue_id'];
        $maintenanceRecord->provider_id = $data['provider_id'];
        $maintenanceRecord->appointment_date = $data['appointment_date'];
        $maintenanceRecord->return_date = $data['return_date'] ?? null;
        $maintenanceRecord->activity_type = $data['activity_type'] ?? null;
        $maintenanceRecord->update();

        if ($maintenanceRecord->issue && array_key_exists('issue_resolved', $data)) {
            if ((bool) $data['issue_resolved']) {
                $maintenanceRecord->issue->status = 'closed';
                $maintenanceRecord->issue->save();
            } else {
                $maintenanceRecord->issue->status = 'in_progress';
                $maintenanceRecord->issue->save();
            }
        }

        return redirect()->route('admin.maintenancerecords.show', $maintenanceRecord->id)->with('status', 'Intervento aggiornato con successo.');
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaintenanceRecord $maintenanceRecord)
    {
        $maintenanceRecord->delete();
        return redirect()->route('admin.maintenancerecords.index')->with('status', 'Intervento eliminato con successo.');
    }

    // --- CUSTOM METHOD ---
    // Metodo per completare un intervento e aggiornare lo stato del guasto associato
    public function complete(Request $request, MaintenanceRecord $maintenanceRecord)
    {
        $data = $request->validate(
            [
                'issue_resolved' => 'required|boolean',
            ],
            [
                'issue_resolved.required' => 'Seleziona se il guasto è stato risolto o meno.',
                'issue_resolved.boolean' => 'Il valore selezionato non è valido.',
            ]
        );

        $maintenanceRecord->loadMissing(['issue', 'deadline', 'vehicle.vehicleType']);

        // Se la manutenzione è legata a una scadenza ministeriale, aggiorna lo stato in base alla risoluzione del guasto
        DB::transaction(function () use ($maintenanceRecord, $data) {
            // 1) complete maintenance
            $maintenanceRecord->return_date = Carbon::today();
            $maintenanceRecord->save();

            // 2) update issue
            if ($maintenanceRecord->issue) {
                if ((bool) $data['issue_resolved']) {
                    $maintenanceRecord->issue->status = 'closed';
                    $maintenanceRecord->issue->save();
                } else {
                    $maintenanceRecord->issue->status = 'in_progress';
                    $maintenanceRecord->issue->save();
                }
            }

            // 3) update current deadline + create next one
            if ($maintenanceRecord->deadline && in_array($maintenanceRecord->deadline->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN], true)) {

                if ((bool) $data['issue_resolved']) {
                    $maintenanceRecord->deadline->status = 'renewed';
                    $maintenanceRecord->deadline->save();
                    $baseDate = Carbon::parse($maintenanceRecord->return_date ?? Carbon::today());
                    $nextDueDate = null;
                    if ($maintenanceRecord->deadline->type === Deadline::TYPE_MINISTERIAL && $maintenanceRecord->vehicle->vehicleType->regular_inspection_months > 0) {
                        $monthsToAdd = (int) $maintenanceRecord->vehicle->vehicleType->regular_inspection_months;
                        $nextDueDate = $baseDate->copy()->addMonthsNoOverflow($monthsToAdd);
                    } elseif ($maintenanceRecord->deadline->type === Deadline::TYPE_OXYGEN && Deadline::supportsOxygenCheckForVehicle($maintenanceRecord->vehicle)) {
                        $nextDueDate = $baseDate->copy()->addMonthsNoOverflow(Deadline::OXYGEN_CHECK_INTERVAL_MONTHS);
                    }
                    if ($nextDueDate) {
                        Deadline::firstOrCreate(
                            [
                                'vehicle_id' => $maintenanceRecord->vehicle_id,
                                'type' => $maintenanceRecord->deadline->type,
                                'due_date' => $nextDueDate->toDateString(),
                            ],
                            [
                                'status' => 'pending',
                            ]
                        );
                    }
                } else {
                    $maintenanceRecord->deadline->status = 'pending';
                    $maintenanceRecord->deadline->save();
                }
            }
        });

        return redirect()
            ->route('admin.maintenancerecords.show', $maintenanceRecord->id)
            ->with('status', 'Intervento completato con successo.');
    }
}
