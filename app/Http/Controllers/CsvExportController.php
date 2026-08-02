<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Issue;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CsvExportController extends Controller
{
    /**
     * Scarica CSV di tutte le entità del modulo specificato.
     */
    public function export(string $entity)
    {
        return match ($entity) {
            'vehicles' => $this->exportVehicles(),
            'issues' => $this->exportIssues(),
            'deadlines' => $this->exportDeadlines(),
            'equipments' => $this->exportEquipments(),
            'maintenance-records' => $this->exportMaintenanceRecords(),
            'mileage-logs' => $this->exportMileageLogs(),
            'providers' => $this->exportProviders(),
            default => abort(404, 'Entità non trovata.'),
        };
    }

    private function exportVehicles()
    {
        $vehicles = Vehicle::with(['brand', 'carModel', 'vehicleType'])->get();

        $headers = ['Sigla', 'Targa', 'Marca', 'Modello', 'Tipo', 'Carburante', 'Immatricolazione', 'Scadenza Garanzia'];
        $rows = $vehicles->map(fn($v) => [
            $v->internal_code,
            $v->license_plate,
            $v->brand?->name ?? '',
            $v->carModel?->name ?? '',
            $v->vehicleType?->name ?? '',
            $v->fuel_type ?? '',
            $v->immatricolation_date_formatted ?? '',
            $v->warranty_expiration_date_formatted ?? '',
        ]);

        return $this->downloadCsv("veicoli.csv", $headers, $rows);
    }

    private function exportIssues()
    {
        $issues = Issue::with('vehicle')->get();

        $headers = ['Veicolo', 'Descrizione', 'Stato', 'Data Evento'];
        $rows = $issues->map(fn($i) => [
            $i->vehicle?->internal_code ?? '',
            $i->description,
            $i->status_label ?? $i->status,
            $i->event_date_formatted ?? '',
        ]);

        return $this->downloadCsv("guasti.csv", $headers, $rows);
    }

    private function exportDeadlines()
    {
        $deadlines = Deadline::with('vehicle')->get();

        $headers = ['Veicolo', 'Tipo', 'Data Scadenza', 'Stato', 'Rinnovata'];
        $rows = $deadlines->map(fn($d) => [
            $d->vehicle?->internal_code ?? '',
            $d->type,
            $d->due_date_formatted ?? '',
            $d->status_label ?? $d->automatic_status,
            $d->is_renewed ? 'Sì' : 'No',
        ]);

        return $this->downloadCsv("scadenze.csv", $headers, $rows);
    }

    private function exportEquipments()
    {
        $equipments = Equipment::with(['vehicle', 'equipmentType'])->get();

        $headers = ['Veicolo', 'Tipo', 'Nome', 'Seriale', 'Data Revisione', 'Scadenza'];
        $rows = $equipments->map(fn($e) => [
            $e->vehicle?->internal_code ?? '',
            $e->equipmentType?->name ?? '',
            $e->name ?? '',
            $e->serial_number ?? '',
            $e->revision_date_formatted ?? '',
            $e->expiration_date_formatted ?? '',
        ]);

        return $this->downloadCsv("attrezzature.csv", $headers, $rows);
    }

    private function exportMaintenanceRecords()
    {
        $records = MaintenanceRecord::with(['vehicle', 'provider'])->get();

        $headers = ['Veicolo', 'Fornitore', 'Data Appuntamento', 'Data Rientro', 'Tipo Attività', 'Km al Servizio'];
        $rows = $records->map(fn($r) => [
            $r->vehicle?->internal_code ?? '',
            $r->provider?->name ?? '',
            $r->appointment_date_formatted ?? '',
            $r->return_date_formatted ?? '',
            $r->activity_type ?? '',
            $r->mileage_at_service ?? '',
        ]);

        return $this->downloadCsv("appuntamenti.csv", $headers, $rows);
    }

    private function exportMileageLogs()
    {
        $logs = MileageLog::with('vehicle')->get();

        $headers = ['Veicolo', 'Data', 'Chilometri'];
        $rows = $logs->map(fn($l) => [
            $l->vehicle?->internal_code ?? '',
            $l->log_date_formatted ?? '',
            $l->mileage,
        ]);

        return $this->downloadCsv("chilometraggi.csv", $headers, $rows);
    }

    private function exportProviders()
    {
        $providers = Provider::all();

        $headers = ['Nome', 'Indirizzo', 'Contatto', 'Tipo'];
        $rows = $providers->map(fn($p) => [
            $p->name,
            $p->address ?? '',
            $p->contact_info ?? '',
            $p->type ?? '',
        ]);

        return $this->downloadCsv("fornitori.csv", $headers, $rows);
    }

    private function downloadCsv(string $filename, array $headers, iterable $rows)
    {
        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');

            // BOM per supporto Excel UTF-8
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($file, $row, ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
