<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MileageLog;
use Illuminate\Console\Command;

class CleanMileageLogTagliandoArtifacts extends Command
{
    // php artisan mileage-logs:clean-tagliando-artifacts [--vehicle=ID] [--apply]
    protected $signature = 'mileage-logs:clean-tagliando-artifacts
        {--vehicle= : Limita la pulizia a un singolo veicolo (id)}
        {--apply : Elimina davvero le righe trovate (di default viene solo mostrata un\'anteprima)}';

    protected $description = 'Trova (ed elimina, con --apply) le righe di mileage_logs create prima della v1.2.8 abbinando erroneamente il km di una scadenza tagliando/cinghia (last_mileage, il km dell\'ultimo cambio) alla sua data di scadenza futura (due_date), invece che alla data reale dell\'appuntamento.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $vehicleId = $this->option('vehicle');

        // Firma dell'artefatto: una riga di mileage_logs il cui
        // (vehicle_id, log_date, mileage) coincide ESATTAMENTE con
        // (vehicle_id, due_date, last_mileage) di una scadenza
        // tagliando/cinghia. Una coincidenza reale su entrambi i valori
        // insieme è praticamente impossibile: è la firma del bug corretto
        // in v1.2.8, non un dato genuino.
        $deadlines = Deadline::whereIn('type', [Deadline::TYPE_TAGLIANDO, Deadline::TYPE_CINGHIA])
            ->whereNotNull('last_mileage')
            ->where('last_mileage', '>', 0)
            ->whereNotNull('due_date')
            ->with('vehicle')
            ->when($vehicleId, fn($q) => $q->where('vehicle_id', $vehicleId))
            ->get();

        $found = 0;

        foreach ($deadlines as $deadline) {
            if (! $deadline->vehicle) {
                continue;
            }

            $match = MileageLog::where('vehicle_id', $deadline->vehicle_id)
                ->whereDate('log_date', $deadline->due_date->toDateString())
                ->where('mileage', $deadline->last_mileage)
                ->first();

            if (! $match) {
                continue;
            }

            $found++;

            $this->line(sprintf(
                '%s Veicolo #%d, %s: %s km (da Scadenza #%d, %s) [mileage_log #%d]',
                $apply ? '[eliminato]' : '[dry-run: da eliminare]',
                $deadline->vehicle_id,
                $deadline->due_date->toDateString(),
                number_format($deadline->last_mileage, 0, ',', '.'),
                $deadline->id,
                $deadline->type,
                $match->id,
            ));

            if ($apply) {
                $match->delete();
            }
        }

        $this->newLine();

        if ($found === 0) {
            $this->info('Nessun artefatto trovato.');

            return self::SUCCESS;
        }

        $this->info(sprintf('%s: %d.', $apply ? 'Eliminati' : 'Da eliminare', $found));

        if (! $apply) {
            $this->warn('Anteprima: rilancia con --apply per eliminarli davvero.');
        }

        return self::SUCCESS;
    }
}
