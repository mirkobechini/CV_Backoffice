<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\SortableAndGroupable;
use App\Http\Controllers\Concerns\StreamsCsv;
use App\Http\Controllers\Controller;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DocumentStatusController extends Controller
{
    use SortableAndGroupable, StreamsCsv;

    /**
     * Tipologie di scadenza mostrate come colonna per ogni veicolo, nello
     * stesso ordine della tabella/form scadenze.
     */
    private const DEADLINE_TYPES = [
        Deadline::TYPE_ASSICURAZIONE,
        Deadline::TYPE_MINISTERIAL,
        Deadline::TYPE_OXYGEN,
        Deadline::TYPE_TAGLIANDO,
        Deadline::TYPE_CINGHIA,
    ];

    /**
     * Vista d'insieme sullo stato di documenti/scadenze della flotta: una
     * riga per veicolo con una colonna per ogni documento/scadenza, per
     * capire a colpo d'occhio cosa manca o sta per scadere senza aprire
     * ogni veicolo singolarmente. Le attrezzature restano in una sezione
     * separata (in previsione di un upload certificati dedicato, non
     * ancora implementato).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Vehicle::class);

        $validated = $request->validate([
            'equipment_status_filter' => 'nullable|in:all,expired,pending,valid',
            'equipment_group_by' => 'nullable|in:type,vehicle',
        ]);
        $equipmentStatusFilter = $validated['equipment_status_filter'] ?? 'all';
        $equipmentGroupBy = $validated['equipment_group_by'] ?? null;

        [$vehicles, $deadlinesByVehicle] = $this->buildVehicleComplianceData();

        // L'attrezzatura non assegnata a un veicolo non ha un gruppo
        // proprio: resta visibile a tutti, come nella dashboard/indice.
        $equipment = Equipment::with('vehicle', 'equipmentType')
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')
                    ->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->get();

        // Stato complessivo (revisione+collaudo insieme), stesso filtro
        // già usato in EquipmentController::index(): l'etichetta è un
        // accessor calcolato, non una colonna, va filtrato in memoria.
        if ($equipmentStatusFilter !== 'all') {
            $labelMap = ['expired' => 'Scaduta', 'pending' => 'In scadenza', 'valid' => 'Valida'];
            $equipment = $equipment->filter(
                fn (Equipment $e) => $e->overall_status_label === $labelMap[$equipmentStatusFilter]
            )->values();
        }

        $groupedEquipment = $this->applyGrouping($equipment, $equipmentGroupBy, function (Equipment $e) use ($equipmentGroupBy) {
            return match ($equipmentGroupBy) {
                'type' => $e->equipmentType->name ?? 'N/A',
                'vehicle' => $e->vehicle?->internal_code ?? 'N/A',
            };
        });

        return view('admin.documents.index', [
            'vehicles' => $vehicles,
            'deadlinesByVehicle' => $deadlinesByVehicle,
            'equipment' => $equipment,
            'groupedEquipment' => $groupedEquipment,
            'deadlineTypes' => self::DEADLINE_TYPES,
            'equipmentStatusFilter' => $equipmentStatusFilter,
            'equipmentGroupBy' => $equipmentGroupBy,
        ]);
    }

    /**
     * Esportazione "conformità flotta" per rendicontazione a enti pubblici
     * (Regione/Protezione Civile): a differenza della vista interna sopra,
     * qui lo stato operativo (disponibile/in officina) conta quanto lo
     * stato scadenze — un ente esterno non ha bisogno del dettaglio per
     * singolo tipo di scadenza, solo di sapere se il veicolo è conforme e
     * disponibile.
     */
    public function exportPdf()
    {
        $this->authorize('viewAny', Vehicle::class);

        $rows = $this->buildComplianceRows();

        $pdf = Pdf::setOption(['defaultFont' => 'DejaVu Sans', 'isHtml5ParserEnabled' => true])
            ->loadView('pdfs.compliance-report', ['rows' => $rows]);

        return $pdf->download('conformita-flotta-'.now()->format('Y-m-d').'.pdf');
    }

    public function exportCsv()
    {
        $this->authorize('viewAny', Vehicle::class);

        $rows = $this->buildComplianceRows();

        $headers = ['Sigla', 'Targa', 'Tipo', 'Stato operativo', 'Stato scadenze', 'Scadenze non in regola', 'Ultimo km'];
        $csvRows = $rows->map(fn (array $r) => [
            $r['vehicle']->internal_code,
            $r['vehicle']->license_plate,
            $r['vehicle']->vehicleType?->name ?? '',
            $r['available'] ? 'Disponibile' : 'In officina',
            $r['compliant'] ? 'In regola' : 'Non in regola',
            implode(', ', $r['non_compliant_types']),
            $r['vehicle']->latestMileageLog?->mileage ?? '',
        ]);

        return $this->downloadCsv('conformita-flotta-'.now()->format('Y-m-d').'.csv', $headers, $csvRows);
    }

    /**
     * @return array{0: Collection<int, Vehicle>, 1: array<int, array<string, Deadline>>}
     */
    private function buildVehicleComplianceData(): array
    {
        $vehicles = Vehicle::with('brand', 'carModel', 'vehicleType', 'latestMileageLog')->forCurrentUser()->get();

        // Stesso raggruppamento "ultima occorrenza per veicolo+tipo" già
        // usato da DeadlineController::index(): l'intera flotta ha poche
        // decine di scadenze, una query con elaborazione in memoria evita
        // N query (una per veicolo+tipo) per costruire la matrice.
        $latestDeadlines = Deadline::with('vehicle')
            ->forCurrentUser()
            ->get()
            ->sortBy(fn (Deadline $d) => [$d->is_renewed ? 1 : 0, $d->id * -1])
            ->unique(fn (Deadline $d) => $d->vehicle_id.'|'.$d->type)
            ->values();

        Deadline::syncStatusesFromRules($latestDeadlines);

        $deadlinesByVehicle = [];
        foreach ($latestDeadlines as $deadline) {
            $deadlinesByVehicle[$deadline->vehicle_id][$deadline->type] = $deadline;
        }

        return [$vehicles, $deadlinesByVehicle];
    }

    /**
     * Una riga per veicolo: stato operativo (disponibile/in officina, stesso
     * criterio della pagina pubblica stato flotta) + stato scadenze (in
     * regola solo se NESSUNA scadenza di tipo periodico risulta scaduta).
     *
     * @return Collection<int, array{vehicle: Vehicle, available: bool, compliant: bool, non_compliant_types: array<int, string>}>
     */
    private function buildComplianceRows()
    {
        [$vehicles, $deadlinesByVehicle] = $this->buildVehicleComplianceData();

        $vehiclesInWorkshop = MaintenanceRecord::forCurrentUser()
            ->currentlyInWorkshop()
            ->pluck('vehicle_id')
            ->unique();

        return $vehicles->map(function (Vehicle $vehicle) use ($deadlinesByVehicle, $vehiclesInWorkshop) {
            $deadlines = $deadlinesByVehicle[$vehicle->id] ?? [];

            $nonCompliantTypes = [];
            foreach (self::DEADLINE_TYPES as $type) {
                if (! $this->deadlineTypeAppliesToVehicle($type, $vehicle)) {
                    continue;
                }

                $deadline = $deadlines[$type] ?? null;

                // Nessun record (mai inserita) è un documento mancante
                // tanto quanto uno scaduto: un veicolo a cui manca del
                // tutto un'assicurazione o una revisione, prima risultava
                // "in regola" per assenza di dati, invece di segnalare la
                // lacuna (audit 2026-10-22, B3).
                if (! $deadline || $deadline->automatic_status === Deadline::STATUS_EXPIRED) {
                    $nonCompliantTypes[] = $type;
                }
            }

            return [
                'vehicle' => $vehicle,
                'available' => ! $vehiclesInWorkshop->contains($vehicle->id),
                'compliant' => empty($nonCompliantTypes),
                'non_compliant_types' => $nonCompliantTypes,
            ];
        })->values();
    }

    /**
     * Assicurazione, Revisione Ministeriale e Tagliando sono obbligatori per
     * qualunque veicolo; Revisione Ossigeno e Cinghia dipendono dalla
     * configurazione del veicolo (stessi criteri già usati altrove per
     * decidere se crearle/richiederle).
     */
    private function deadlineTypeAppliesToVehicle(string $type, Vehicle $vehicle): bool
    {
        return match ($type) {
            Deadline::TYPE_OXYGEN => Deadline::supportsOxygenCheckForVehicle($vehicle),
            Deadline::TYPE_CINGHIA => $vehicle->needsTimingBeltDeadline(),
            default => true,
        };
    }
}
