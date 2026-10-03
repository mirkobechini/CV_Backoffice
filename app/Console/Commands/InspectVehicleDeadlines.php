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
        } else {
            $this->info($deadlines->count() . ' scadenza/e trovate (incluse quelle eliminate):');
            $this->newLine();
        }

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

        // Cercata sempre, non solo quando $deadlines è vuota: un veicolo
        // può avere altre scadenze (tagliando, revisione...) ma mancare
        // proprio del tipo cercato se quella riga è stata eliminata
        // bypassando il soft delete (es. DELETE grezzo) — $deadlines non
        // sarebbe vuota in quel caso, solo priva di quel tipo specifico.
        $this->reportOrphanActivityTraces($vehicle, $type, $deadlines->pluck('id')->all());
    }

    /**
     * Cerca nel registro attività tracce di scadenze (di questo veicolo, ed
     * eventualmente di questo tipo) il cui subject_id non è tra quelli già
     * trovati con withTrashed(): significa che quella riga non esiste più
     * da nessuna parte (eliminata bypassando il soft delete, es. con un
     * DELETE grezzo). Si cerca nelle properties salvate (vehicle_id/type),
     * non per subject_id, perché per una riga sparita del tutto non lo
     * conosciamo più.
     */
    private function reportOrphanActivityTraces(Vehicle $vehicle, ?string $type, array $knownIds): void
    {
        $activities = Activity::where('subject_type', Deadline::class)
            ->where('properties', 'like', '%"vehicle_id":' . $vehicle->id . '%')
            ->when($type, fn ($q) => $q->where('properties', 'like', '%"type":"' . $type . '"%'))
            ->whereNotIn('subject_id', $knownIds)
            ->with('causer')
            ->oldest()
            ->get();

        if ($activities->isEmpty()) {
            if (empty($knownIds)) {
                $this->line('Nessuna traccia nemmeno nel registro attività per questo veicolo' . ($type ? " di tipo \"{$type}\"" : '') . ': la scadenza non è mai esistita, oppure il registro non la copre.');
            }

            return;
        }

        $this->warn('Il registro attività ha tracce di scadenze per questo veicolo' . ($type ? " di tipo \"{$type}\"" : '') . ' che non esistono più in nessuna forma (riga eliminata senza soft delete, es. DELETE grezzo):');
        foreach ($activities as $a) {
            $causer = $a->causer?->name ?? 'sistema/sconosciuto';
            $this->line("  [{$a->created_at}] subject_id={$a->subject_id} {$a->description} — {$causer}");
            $this->line('  properties: ' . $a->properties);
        }
    }
}
