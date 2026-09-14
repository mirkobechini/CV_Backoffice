<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use Illuminate\Console\Command;

class BackfillDeadlineRenewalLinks extends Command
{
    // php artisan deadlines:backfill-renewal-links [--vehicle=ID] [--apply]
    protected $signature = 'deadlines:backfill-renewal-links
        {--vehicle= : Limita il backfill a un singolo veicolo (id)}
        {--apply : Applica davvero le modifiche (di default viene solo mostrata un\'anteprima)}';

    protected $description = 'Collega retroattivamente le scadenze periodiche (Revisione Ministeriale/Impianto Ossigeno/Tagliando) alla scadenza che rinnovano (renews_deadline_id), per dati creati prima che questo collegamento esistesse o con collegamenti mancanti.';

    /**
     * Tipi per cui ha senso una catena di rinnovi (stessi tipi gestiti da
     * DeadlineService::createNextDeadlineAfterRenewal).
     */
    private const CHAIN_TYPES = [
        Deadline::TYPE_MINISTERIAL,
        Deadline::TYPE_OXYGEN,
        Deadline::TYPE_TAGLIANDO,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $vehicleId = $this->option('vehicle');

        $query = Deadline::whereIn('type', self::CHAIN_TYPES)
            ->orderBy('vehicle_id')
            ->orderBy('type')
            ->orderBy('due_date');

        if ($vehicleId) {
            $query->where('vehicle_id', $vehicleId);
        }

        // Raggruppa per veicolo+tipo: ogni gruppo è una catena di rinnovi
        // indipendente. Ordinando per due_date, ogni scadenza (tranne la
        // prima) è per definizione quella che rinnova la precedente.
        $groups = $query->get()->groupBy(fn (Deadline $d) => $d->vehicle_id . '|' . $d->type);

        $toLink = [];

        foreach ($groups as $group) {
            $group = $group->values();

            for ($i = 1; $i < $group->count(); $i++) {
                $previous = $group[$i - 1];
                $current = $group[$i];

                // Non tocchiamo collegamenti già presenti: potrebbero non
                // coincidere con il semplice ordinamento per data (es. una
                // correzione manuale), e non è compito di questo comando
                // deciderlo al posto di chi l'ha impostato.
                if ($current->renews_deadline_id !== null) {
                    continue;
                }

                $toLink[] = [$current, $previous];
            }
        }

        if (empty($toLink)) {
            $this->info('Nessuna scadenza da collegare: tutte le catene risultano già collegate.');

            return self::SUCCESS;
        }

        foreach ($toLink as [$current, $previous]) {
            $this->line(sprintf(
                '%s Veicolo #%d, %s: #%d (scad. %s) -> rinnova #%d (scad. %s)',
                $apply ? '[OK]' : '[dry-run]',
                $current->vehicle_id,
                $current->type,
                $current->id,
                optional($current->due_date)->toDateString() ?? '—',
                $previous->id,
                optional($previous->due_date)->toDateString() ?? '—',
            ));

            if ($apply) {
                $current->update(['renews_deadline_id' => $previous->id]);
            }
        }

        $count = count($toLink);

        if (! $apply) {
            $this->newLine();
            $this->warn("Anteprima: {$count} collegamento/i verrebbero creati. Rilancia con --apply per applicarli davvero.");
        } else {
            $this->newLine();
            $this->info("{$count} collegamento/i creati.");
        }

        return self::SUCCESS;
    }
}
