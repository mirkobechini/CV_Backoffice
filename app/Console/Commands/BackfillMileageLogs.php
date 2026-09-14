<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MaintenanceRecord;
use App\Services\MileageLogService;
use Illuminate\Console\Command;

class BackfillMileageLogs extends Command
{
    // php artisan mileage-logs:backfill [--vehicle=ID] [--apply]
    protected $signature = 'mileage-logs:backfill
        {--vehicle= : Limita il backfill a un singolo veicolo (id)}
        {--apply : Applica davvero le modifiche (di default viene solo mostrata un\'anteprima)}';

    protected $description = 'Registra nello storico chilometraggi (mileage_logs) le letture km già presenti su scadenze (last_mileage) e appuntamenti (mileage_at_service) ma mai riportate lì, per dati inseriti prima che questo collegamento esistesse.';

    public function __construct(
        private readonly MileageLogService $mileageLogService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $vehicleId = $this->option('vehicle');

        // Un unico elenco di letture candidate (veicolo, data, km, etichetta
        // per il log), da entrambe le fonti, ordinate per data crescente:
        // così, quando una lettura successiva viene verificata, quelle
        // precedenti dello stesso veicolo sono già state applicate (se
        // --apply) e la verifica di coerenza cronologica le vede.
        $deadlines = Deadline::whereNotNull('last_mileage')
            ->whereNotNull('due_date')
            ->with('vehicle')
            ->when($vehicleId, fn($q) => $q->where('vehicle_id', $vehicleId))
            ->get()
            ->map(fn(Deadline $d) => [
                'vehicle' => $d->vehicle,
                'date' => $d->due_date,
                'mileage' => $d->last_mileage,
                'label' => "Scadenza #{$d->id} ({$d->type})",
            ]);

        $maintenanceRecords = MaintenanceRecord::whereNotNull('mileage_at_service')
            ->whereNotNull('return_date')
            ->with('vehicle')
            ->when($vehicleId, fn($q) => $q->where('vehicle_id', $vehicleId))
            ->get()
            ->map(fn(MaintenanceRecord $r) => [
                'vehicle' => $r->vehicle,
                'date' => $r->return_date,
                'mileage' => $r->mileage_at_service,
                'label' => "Appuntamento #{$r->id}",
            ]);

        $candidates = $deadlines->concat($maintenanceRecords)
            ->filter(fn(array $c) => $c['vehicle'] !== null)
            ->sortBy(fn(array $c) => $c['date']->toDateString())
            ->values();

        if ($candidates->isEmpty()) {
            $this->info('Nessuna lettura km da registrare.');

            return self::SUCCESS;
        }

        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'conflict' => 0];

        foreach ($candidates as $candidate) {
            $result = $this->mileageLogService->recordReading(
                $candidate['vehicle'],
                $candidate['date'],
                $candidate['mileage'],
                $apply,
            );

            if (! isset($counts[$result])) {
                continue;
            }
            $counts[$result]++;

            if ($result === 'unchanged') {
                continue;
            }

            $icon = match ($result) {
                'created' => $apply ? '[OK]' : '[dry-run: da creare]',
                'updated' => $apply ? '[OK]' : '[dry-run: da aggiornare]',
                'conflict' => '[saltato: incoerente con la cronologia]',
                default => '',
            };

            $this->line(sprintf(
                '%s Veicolo #%d, %s: %s -> %s km (%s)',
                $icon,
                $candidate['vehicle']->id,
                $candidate['date']->toDateString(),
                number_format($candidate['mileage'], 0, ',', '.'),
                $candidate['label'],
                $result,
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d, aggiornati: %d, invariati: %d, saltati per conflitto: %d.',
            $apply ? 'Registrati' : 'Da registrare',
            $counts['created'],
            $counts['updated'],
            $counts['unchanged'],
            $counts['conflict'],
        ));

        if (! $apply && ($counts['created'] + $counts['updated']) > 0) {
            $this->warn('Anteprima: rilancia con --apply per applicare davvero.');
        }

        return self::SUCCESS;
    }
}
