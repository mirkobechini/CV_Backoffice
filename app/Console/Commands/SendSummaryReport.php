<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Models\Issue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReportMail;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\MaintenanceRecord;
use App\Models\NotificationSetting;
use Carbon\Carbon;

class SendSummaryReport extends Command
{
    // Come si chiama il comando da terminale
    // php artisan app:send-summary-report
    protected $signature = 'app:send-summary-report';

    // Descrizione (compare in php artisan list)
    protected $description = 'Invia un report riassuntivo dello stato dei veicoli e delle manutenzioni';

    /**
     * Execute the console command.
     *
     * Il report è personale per account: ogni utente ha una propria email
     * destinataria, frequenza e numero di giorni di preavviso. Il comando
     * viene lanciato ogni giorno dallo scheduler; qui si decide, per ciascun
     * destinatario configurato, se "oggi" è il suo giorno di invio.
     */
    public function handle()
    {
        $recipientRows = NotificationSetting::where('key', 'report_email')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->get(['user_id', 'value']);

        if ($recipientRows->isEmpty()) {
            $this->warn('Nessun destinatario configurato. Ogni utente imposta la propria email nelle impostazioni notifiche.');

            return Command::SUCCESS;
        }

        // Parti del report indipendenti dai giorni di preavviso: calcolate una
        // sola volta e riutilizzate per tutti i destinatari.
        $totalVehicles = Vehicle::count();
        $allVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')->get();

        $vehicleIdsWithOpenIssues = Issue::open()->distinct('vehicle_id')->pluck('vehicle_id');
        $vehiclesOk = $allVehicles->reject(fn ($v) => $vehicleIdsWithOpenIssues->contains($v->id))->count();

        $incompleteVehicles = $allVehicles->filter(fn ($v) => ! $v->hasAllRequiredEquipment());
        $openIssues = Issue::with('vehicle')->open()->get();
        $expiredDeadlines = Deadline::where('status', Deadline::STATUS_EXPIRED)->where('is_renewed', false)->get();
        $upcomingAppointments = MaintenanceRecord::with('vehicle', 'provider', 'items.itemable')
            ->whereNull('return_date')
            ->where('appointment_date', '>=', today())
            ->orderBy('appointment_date')
            ->take(5)
            ->get();
        $vehiclesInMaintenance = MaintenanceRecord::whereNull('return_date')->distinct('vehicle_id')->count('vehicle_id');

        $today = Carbon::today();
        $sentCount = 0;

        foreach ($recipientRows as $row) {
            $userId = $row->user_id;
            $frequency = NotificationSetting::where('user_id', $userId)->where('key', 'report_frequency')->value('value') ?? 'daily';

            $isSendDay = match ($frequency) {
                'weekly' => $today->isMonday(),
                'monthly' => $today->day === 1,
                default => true, // daily
            };

            if (! $isSendDay) {
                continue;
            }

            $reminderDays = (int) (NotificationSetting::where('user_id', $userId)->where('key', 'reminder_days_before')->value('value') ?? 7);

            $data = [
                'totalVehicles' => $totalVehicles,
                'vehiclesOk' => $vehiclesOk,
                'openIssues' => $openIssues,
                'expiredDeadlines' => $expiredDeadlines,
                'upcomingDeadlines' => Deadline::with('vehicle')->upcoming($reminderDays)->get(),
                'upcomingAppointments' => $upcomingAppointments,
                'incompleteVehicles' => $incompleteVehicles,
                'vehiclesInMaintenance' => $vehiclesInMaintenance,
                'expiringEquipment' => Equipment::with('vehicle')->expiringSoon($reminderDays)->get(),
            ];

            Mail::to($row->value)->send(new ReportMail($data));
            $this->info("Report inviato con successo a {$row->value}!");
            $sentCount++;
        }

        if ($sentCount === 0) {
            $this->info('Nessun report da inviare oggi.');
        }

        return Command::SUCCESS;
    }
}
