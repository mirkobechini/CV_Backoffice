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

        // La sigla (internal_code) di questa flotta è numerica (es. "1744"),
        // quindi non si può usare "è tutta cifre" per distinguerla da un id:
        // si cerca sempre prima per sigla, e solo se non trovata per id.
        $vehicle = Vehicle::where('internal_code', $identifier)->first()
            ?? Vehicle::find($identifier);

        if (! $vehicle) {
            $this->error("Veicolo \"{$identifier}\" non trovato.");

            return self::FAILURE;
        }

        $this->info("Veicolo #{$vehicle->id} — sigla {$vehicle->internal_code}, targa {$vehicle->license_plate}, timing_belt_type: " . ($vehicle->timing_belt_type ?? 'NULL'));
        $this->newLine();

        $query = Deadline::withTrashed()->where('vehicle_id', $vehicle->id);
        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }
        $deadlines = $query->orderBy('id')->get();

        if ($deadlines->isEmpty()) {
            $this->warn('Nessuna scadenza trovata (nemmeno eliminata) per questo veicolo' . ($type ? " di tipo \"{$type}\"" : '') . '.');

            return self::SUCCESS;
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

        return self::SUCCESS;
    }
}
