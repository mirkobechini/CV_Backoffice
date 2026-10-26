<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StreamsCsv;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\FleetDevice;
use App\Models\FuelCard;
use App\Models\FuelLog;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\Vehicle;

class CsvExportController extends Controller
{
    use StreamsCsv;

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
            'fuel-logs' => $this->exportFuelLogs(),
            'fleet-devices' => $this->exportFleetDevices(),
            'fuel-cards' => $this->exportFuelCards(),
            'providers' => $this->exportProviders(),
            default => abort(404, 'Entità non trovata.'),
        };
    }

    private function exportVehicles()
    {
        $vehicles = Vehicle::with(['brand', 'carModel', 'vehicleType'])->forCurrentUser()->get();

        $headers = ['Sigla', 'Targa', 'Marca', 'Modello', 'Tipo', 'Carburante', 'Immatricolazione', 'Scadenza Garanzia'];
        $rows = $vehicles->map(fn ($v) => [
            $v->internal_code,
            $v->license_plate,
            $v->brand?->name ?? '',
            $v->carModel?->name ?? '',
            $v->vehicleType?->name ?? '',
            $v->fuel_type ?? '',
            $v->immatricolation_date_formatted ?? '',
            $v->warranty_expiration_date_formatted ?? '',
        ]);

        return $this->downloadCsv('veicoli.csv', $headers, $rows);
    }

    private function exportIssues()
    {
        $issues = Issue::with('vehicle')->forCurrentUser()->get();

        $headers = ['Veicolo', 'Descrizione', 'Stato', 'Data Evento'];
        $rows = $issues->map(fn ($i) => [
            $i->vehicle?->internal_code ?? '',
            $i->description,
            $i->status_label ?? $i->status,
            $i->event_date_formatted ?? '',
        ]);

        return $this->downloadCsv('guasti.csv', $headers, $rows);
    }

    private function exportDeadlines()
    {
        $deadlines = Deadline::with('vehicle', 'insuranceCoverages')->forCurrentUser()->get();

        $headers = ['Veicolo', 'Tipo', 'Data Scadenza', 'Stato', 'Rinnovata', 'Compagnia', 'Numero Polizza', 'Coperture', 'Premio', 'Massimale', 'Broker'];
        $rows = $deadlines->map(fn ($d) => [
            $d->vehicle?->internal_code ?? '',
            $d->type,
            $d->due_date_formatted ?? '',
            $d->status_label ?? $d->automatic_status,
            $d->is_renewed ? 'Sì' : 'No',
            $d->insurance_company ?? '',
            $d->insurance_policy_number ?? '',
            $d->insuranceCoverages->map(fn ($c) => $c->coverage_type.' (€'.number_format((float) $c->cost, 2, ',', '.').')')->implode('; '),
            $d->type === Deadline::TYPE_ASSICURAZIONE ? number_format($d->insurance_premium_total, 2, ',', '') : '',
            $d->insurance_coverage_limit !== null ? number_format((float) $d->insurance_coverage_limit, 2, ',', '') : '',
            $d->insurance_broker_contact ?? '',
        ]);

        return $this->downloadCsv('scadenze.csv', $headers, $rows);
    }

    private function exportEquipments()
    {
        // L'attrezzatura non assegnata a un veicolo non ha un gruppo proprio:
        // resta inclusa, come nell'indice.
        $equipments = Equipment::with(['vehicle', 'equipmentType'])
            ->where(function ($q) {
                $q->whereDoesntHave('vehicle')
                    ->orWhereHas('vehicle', fn ($vq) => $vq->forCurrentUser());
            })
            ->get();

        $headers = ['Veicolo', 'Tipo', 'Nome', 'Seriale', 'Data Revisione', 'Scadenza'];
        $rows = $equipments->map(fn ($e) => [
            $e->vehicle?->internal_code ?? '',
            $e->equipmentType?->name ?? '',
            $e->name ?? '',
            $e->serial_number ?? '',
            $e->revision_date_formatted ?? '',
            $e->expiration_date_formatted ?? '',
        ]);

        return $this->downloadCsv('attrezzature.csv', $headers, $rows);
    }

    private function exportMaintenanceRecords()
    {
        $records = MaintenanceRecord::with(['vehicle', 'provider'])->forCurrentUser()->get();

        $headers = ['Veicolo', 'Fornitore', 'Data Appuntamento', 'Data Rientro', 'Tipo Attività', 'Km al Servizio', 'Costo'];
        $rows = $records->map(fn ($r) => [
            $r->vehicle?->internal_code ?? '',
            $r->provider?->name ?? '',
            $r->appointment_date_formatted ?? '',
            $r->return_date_formatted ?? '',
            $r->activity_type ?? '',
            $r->mileage_at_service ?? '',
            $r->cost !== null ? number_format((float) $r->cost, 2, ',', '') : '',
        ]);

        return $this->downloadCsv('appuntamenti.csv', $headers, $rows);
    }

    private function exportMileageLogs()
    {
        $logs = MileageLog::with('vehicle')->forCurrentUser()->get();

        $headers = ['Veicolo', 'Data', 'Chilometri'];
        $rows = $logs->map(fn ($l) => [
            $l->vehicle?->internal_code ?? '',
            $l->log_date_formatted ?? '',
            $l->mileage,
        ]);

        return $this->downloadCsv('chilometraggi.csv', $headers, $rows);
    }

    private function exportFuelLogs()
    {
        $logs = FuelLog::with('vehicle', 'fuelCard')->forCurrentUser()->get();

        $headers = ['Veicolo', 'Data', 'Litri', 'Costo totale', 'Chilometri', 'Tessera'];
        $rows = $logs->map(fn ($l) => [
            $l->vehicle?->internal_code ?? '',
            $l->log_date_formatted ?? '',
            $l->liters,
            $l->cost_total ?? '',
            $l->mileage ?? '',
            $l->fuelCard?->card_number ?? '',
        ]);

        return $this->downloadCsv('rifornimenti.csv', $headers, $rows);
    }

    private function exportFleetDevices()
    {
        // FleetDevice ha un group_id proprio (non dedotto dal veicolo, che
        // può essere assente per un dispositivo non ancora assegnato): il
        // filtro è diretto sul gruppo attivo, non su whereHas('vehicle').
        $groupId = auth()->user()?->activeGroup()?->id;

        $devices = FleetDevice::with('vehicle')->where('group_id', $groupId)->get();

        $headers = ['Tipo', 'Codice', 'Veicolo', 'Targa'];
        $rows = $devices->map(fn ($d) => [
            $d->type_label,
            $d->code,
            $d->vehicle?->internal_code ?? '',
            $d->vehicle?->license_plate ?? '',
        ]);

        return $this->downloadCsv('dispositivi.csv', $headers, $rows);
    }

    private function exportFuelCards()
    {
        $groupId = auth()->user()?->activeGroup()?->id;

        $cards = FuelCard::with('vehicle')->where('group_id', $groupId)->get();

        $headers = ['Numero tessera', 'Fornitore', 'Stato', 'Veicolo'];
        $rows = $cards->map(fn ($c) => [
            $c->card_number,
            $c->provider ?? '',
            $c->status_label,
            $c->vehicle?->internal_code ?? '',
        ]);

        return $this->downloadCsv('tessere-carburante.csv', $headers, $rows);
    }

    private function exportProviders()
    {
        $providers = Provider::all();

        $headers = ['Nome', 'Indirizzo', 'Contatto', 'Tipo'];
        $rows = $providers->map(fn ($p) => [
            $p->name,
            $p->address ?? '',
            $p->contact_info ?? '',
            $p->type ?? '',
        ]);

        return $this->downloadCsv('fornitori.csv', $headers, $rows);
    }
}
