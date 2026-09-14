<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Vehicle;
use Illuminate\Console\Command;

class MileageTimeline extends Command
{
    // php artisan mileage-logs:timeline {vehicle}
    protected $signature = 'mileage-logs:timeline {vehicle : ID del veicolo}';

    protected $description = 'Mostra, in ordine cronologico, ogni lettura km nota per un veicolo (storico chilometraggi, scadenze, appuntamenti), segnalando quelle fuori ordine — utile per capire un conflitto segnalato da mileage-logs:backfill.';

    public function handle(): int
    {
        $vehicle = Vehicle::find($this->argument('vehicle'));

        if (! $vehicle) {
            $this->error('Veicolo non trovato.');

            return self::FAILURE;
        }

        $rows = collect();

        foreach (MileageLog::where('vehicle_id', $vehicle->id)->get() as $log) {
            $rows->push([
                'date' => $log->log_date,
                'mileage' => $log->mileage,
                'source' => "mileage_logs #{$log->id}",
            ]);
        }

        foreach (Deadline::where('vehicle_id', $vehicle->id)->whereNotNull('last_mileage')->where('last_mileage', '>', 0)->get() as $d) {
            // Solo ministeriale/ossigeno: per tagliando/cinghia due_date non
            // è la data della lettura (vedi mileage-logs:backfill).
            if (! in_array($d->type, [Deadline::TYPE_MINISTERIAL, Deadline::TYPE_OXYGEN], true) || ! $d->due_date) {
                continue;
            }
            $rows->push([
                'date' => $d->due_date,
                'mileage' => $d->last_mileage,
                'source' => "Scadenza #{$d->id} ({$d->type})",
            ]);
        }

        foreach (MaintenanceRecord::where('vehicle_id', $vehicle->id)->whereNotNull('mileage_at_service')->where('mileage_at_service', '>', 0)->whereNotNull('return_date')->get() as $r) {
            $rows->push([
                'date' => $r->return_date,
                'mileage' => $r->mileage_at_service,
                'source' => "Appuntamento #{$r->id}",
            ]);
        }

        if ($rows->isEmpty()) {
            $this->info('Nessuna lettura km trovata per questo veicolo.');

            return self::SUCCESS;
        }

        $sorted = $rows->sortBy(fn($r) => $r['date']->toDateString())->values();

        $this->info("Veicolo #{$vehicle->id} ({$vehicle->internal_code}) — " . $sorted->count() . ' letture:');
        $this->newLine();

        $previousMileage = null;
        foreach ($sorted as $row) {
            $isDrop = $previousMileage !== null && $row['mileage'] < $previousMileage;

            $this->line(sprintf(
                '%s  %10s km   %s',
                $row['date']->toDateString(),
                number_format($row['mileage'], 0, ',', '.'),
                $row['source'],
            ));

            if ($isDrop) {
                $this->warn('  ^ inferiore alla lettura precedente');
            }

            $previousMileage = $row['mileage'];
        }

        return self::SUCCESS;
    }
}
