<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\Vehicle;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

class InspectVehicleDeadlines extends Command
{
    // php artisan deadlines:inspect {vehicle} {--type=}
    protected $signature = 'deadlines:inspect {vehicle : Sigla (internal_code) o ID del veicolo} {--type= : Filtra per tipo scadenza, es. "Cinghia Distribuzione"}';

    protected $description = 'Diagnostico di sola lettura: mostra tutte le scadenze di un veicolo (comprese quelle eliminate/soft-deleted) con il relativo registro attività, per capire cosa è successo a una scadenza che sembra sparita. Non modifica nulla.';

    public function handle(): int
    {
        $identifier = $this->argument('vehicle');

        // withTrashed(): un veicolo eliminato e "ricreato" con la stessa
        // sigla (es. per correggere un errore) lascerebbe altrimenti le
        // sue scadenze storiche del tutto invisibili a questo comando,
        // perché agganciate al vecchio id ormai fuori dalla ricerca
        // normale. La sigla (internal_code) di questa flotta è numerica
        // (es. "1744"), quindi non si può usare "è tutta cifre" per
        // distinguerla da un id: si cerca sempre prima per sigla, e solo
        // se non trovata per id.
        $vehicles = Vehicle::withTrashed()->where('internal_code', $identifier)->get();
        if ($vehicles->isEmpty()) {
            $vehicle = Vehicle::withTrashed()->find($identifier);
            $vehicles = $vehicle ? collect([$vehicle]) : collect();
        }

        if ($vehicles->isEmpty()) {
            $this->error("Veicolo \"{$identifier}\" non trovato (nemmeno tra quelli eliminati).");

            return self::FAILURE;
        }

        if ($vehicles->count() > 1) {
            $this->warn("Trovati {$vehicles->count()} veicoli con sigla \"{$identifier}\" (es. un duplicato eliminato e ricreato): li mostro tutti.");
            $this->newLine();
        }

        foreach ($vehicles as $vehicle) {
            $this->inspectVehicle($vehicle);
        }

        return self::SUCCESS;
    }

    private function inspectVehicle(Vehicle $vehicle): void
    {
        $this->info("Veicolo #{$vehicle->id} — sigla {$vehicle->internal_code}, targa {$vehicle->license_plate}, timing_belt_type: " . ($vehicle->timing_belt_type ?? 'NULL') . ($vehicle->trashed() ? ' | VEICOLO ELIMINATO il ' . $vehicle->deleted_at : ''));
        $this->newLine();

        $query = Deadline::withTrashed()->where('vehicle_id', $vehicle->id);
        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }
        $deadlines = $query->orderBy('id')->get();

        if ($deadlines->isEmpty()) {
            $this->warn('Nessuna scadenza trovata (nemmeno eliminata, via withTrashed) per questo veicolo' . ($type ? " di tipo \"{$type}\"" : '') . '.');
            $this->reportOrphanActivityTraces($vehicle, $type);
            $this->newLine();

            return;
        }

        $this->info($deadlines->count() . ' scadenza/e trovate (incluse quelle eliminate):');
        $this->newLine();

        foreach ($deadlines as $d) {
            $this->line("— Scadenza #{$d->id} [{$d->type}]");
            $this->line("  status: {$d->status} | is_renewed: " . ($d->is_renewed ? 'sì' : 'no') . ($d->trashed() ? ' | ELIMINATA il ' . $d->deleted_at : ''));
            $this->line('  due_date: ' . ($d->due_date?->toDateString() ?? 'NULL') . ' | interval_km: ' . ($d->interval_km ?? 'NULL') . ' | last_mileage: ' . ($d->last_mileage ?? 'NULL'));
            $this->line('  renews_deadline_id: ' . ($d->renews_deadline_id ?? '—') . ' | creata: ' . $d->created_at . ' | aggiornata: ' . $d->updated_at);

            $activities = Activity::where('subject_type', Deadline::class)
                ->where('subject_id', $d->id)
                ->with('causer')
                ->oldest()
                ->get();

            if ($activities->isEmpty()) {
                $this->line('  (nessun evento nel registro attività per questa scadenza)');
            } else {
                foreach ($activities as $a) {
                    $causer = $a->causer?->name ?? 'sistema/sconosciuto';
                    $this->line("  [{$a->created_at}] {$a->description} — {$causer}");
                }
            }

            $this->newLine();
        }
    }

    /**
     * Ultima spiaggia: se non c'è nessuna riga nemmeno con withTrashed(),
     * la scadenza potrebbe essere stata eliminata "per davvero" (bypassando
     * il soft delete, es. con un DELETE grezzo). In quel caso la riga non
     * esiste più da nessuna parte, ma il registro attività (tabella
     * separata) può comunque avere ancora traccia di quando esisteva,
     * cercando nelle properties salvate invece che per subject_id (che qui
     * non conosciamo più).
     */
    private function reportOrphanActivityTraces(Vehicle $vehicle, ?string $type): void
    {
        $activities = Activity::where('subject_type', Deadline::class)
            ->where('properties', 'like', '%"vehicle_id":' . $vehicle->id . '%')
            ->when($type, fn ($q) => $q->where('properties', 'like', '%"type":"' . $type . '"%'))
            ->with('causer')
            ->oldest()
            ->get();

        if ($activities->isEmpty()) {
            $this->line('Nessuna traccia nemmeno nel registro attività per questo veicolo' . ($type ? " di tipo \"{$type}\"" : '') . ': la scadenza non è mai esistita, oppure il registro non la copre.');

            return;
        }

        $this->warn('Ma il registro attività ha tracce di scadenze per questo veicolo che non esistono più in nessuna forma (riga eliminata senza soft delete, es. DELETE grezzo):');
        foreach ($activities as $a) {
            $causer = $a->causer?->name ?? 'sistema/sconosciuto';
            $this->line("  [{$a->created_at}] subject_id={$a->subject_id} {$a->description} — {$causer}");
            $this->line('  properties: ' . $a->properties);
        }
    }
}
