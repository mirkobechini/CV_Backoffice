<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use Illuminate\Console\Command;

class FixMisattachedRevisionMileage extends Command
{
    // php artisan deadlines:fix-misattached-revision-mileage [--vehicle=ID] [--apply]
    protected $signature = 'deadlines:fix-misattached-revision-mileage
        {--vehicle= : Limita la correzione a un singolo veicolo (id)}
        {--apply : Applica davvero la correzione (di default viene solo mostrata un\'anteprima)}';

    protected $description = 'Corregge le Revisioni Ministeriale/Impianto Ossigeno create prima del fix: il km inserito con "Nuova scadenza" mentre ce n\'era già una in attesa finiva sulla scadenza NUOVA (futura) invece che su quella appena rinnovata. Sposta last_mileage dal figlio (via renews_deadline_id) al genitore, quando il genitore non ne ha già uno.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $vehicleId = $this->option('vehicle');

        // Il figlio (renews_deadline_id valorizzato) ha il km che in realtà
        // appartiene al genitore appena rinnovato: lo spostiamo solo se il
        // genitore non ha già un suo valore (per non sovrascrivere una
        // correzione manuale già fatta o un dato inserito correttamente).
        $misattached = Deadline::whereIn('type', [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN])
            ->whereNotNull('renews_deadline_id')
            ->whereNotNull('last_mileage')
            ->where('last_mileage', '>', 0)
            ->with(['vehicle', 'renewsDeadline'])
            ->when($vehicleId, fn($q) => $q->where('vehicle_id', $vehicleId))
            ->get()
            ->filter(fn(Deadline $child) => $child->renewsDeadline && ! $child->renewsDeadline->last_mileage);

        if ($misattached->isEmpty()) {
            $this->info('Nessuna scadenza da correggere.');

            return self::SUCCESS;
        }

        foreach ($misattached as $child) {
            $parent = $child->renewsDeadline;

            $this->line(sprintf(
                '%s Veicolo #%d, %s: %s km sposta da Scadenza #%d (scad. %s, futura) a Scadenza #%d (scad. %s, quella appena rinnovata)',
                $apply ? '[OK]' : '[dry-run]',
                $child->vehicle_id,
                $child->type,
                number_format($child->last_mileage, 0, ',', '.'),
                $child->id,
                $child->due_date?->toDateString() ?? '—',
                $parent->id,
                $parent->due_date?->toDateString() ?? '—',
            ));

            if ($apply) {
                $parent->update(['last_mileage' => $child->last_mileage]);
                $child->update(['last_mileage' => null]);
            }
        }

        $this->newLine();
        $this->info(sprintf('%s: %d.', $apply ? 'Corrette' : 'Da correggere', $misattached->count()));

        if (! $apply) {
            $this->warn('Anteprima: rilancia con --apply per applicare davvero. Esegui poi mileage-logs:backfill per registrare le letture corrette nello storico chilometraggi.');
        } else {
            $this->info('Ora esegui mileage-logs:backfill per registrare le letture corrette nello storico chilometraggi.');
        }

        return self::SUCCESS;
    }
}
