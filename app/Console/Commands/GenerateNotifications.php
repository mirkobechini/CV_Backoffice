<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Issue;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateNotifications extends Command
{
    // php artisan app:generate-notifications
    protected $signature = 'app:generate-notifications';

    protected $description = 'Genera notifiche in-app per scadenze, guasti e attrezzature in scadenza';

    public function handle(NotificationService $notifications)
    {
        $created = 0;

        // 1) Scadenze in scadenza (entro reminder_days_before)
        $reminderDays = (int) (DB::table('notification_settings')->where('key', 'reminder_days_before')->value('value') ?? 7);
        $upcomingDeadlines = Deadline::with('vehicle')
            ->where('is_renewed', false)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [today(), today()->addDays($reminderDays)])
            ->get();

        foreach ($upcomingDeadlines as $deadline) {
            $vehicleCode = $deadline->vehicle?->internal_code ?? 'N/A';
            $title = "Scadenza in arrivo: {$deadline->type}";
            $message = "Il veicolo {$vehicleCode} ha una scadenza ({$deadline->type}) il {$deadline->due_date?->format('d/m/Y')}.";
            $url = $deadline->vehicle_id ? route('admin.vehicles.show', $deadline->vehicle_id) : null;

            if (!$this->alreadyNotified(Notification::TYPE_DEADLINE, $deadline->id)) {
                $notifications->notifyAdmins(Notification::TYPE_DEADLINE, $title, $message . " #deadline-{$deadline->id}", $url);
                $created++;
            }
        }

        // 2) Guasti aperti/in lavorazione
        $openIssues = Issue::with('vehicle')->open()->get();

        foreach ($openIssues as $issue) {
            $vehicleCode = $issue->vehicle?->internal_code ?? 'N/A';
            $title = "Guasto aperto: {$vehicleCode}";
            $message = $issue->description;
            $url = $issue->vehicle_id ? route('admin.vehicles.show', $issue->vehicle_id) : null;

            if (!$this->alreadyNotified(Notification::TYPE_ISSUE, $issue->id)) {
                $notifications->notifyAdmins(Notification::TYPE_ISSUE, $title, $message . " #issue-{$issue->id}", $url);
                $created++;
            }
        }

        // 3) Attrezzature in scadenza
        $expiringEquipment = Equipment::with('vehicle')->expiringSoon($reminderDays)->get();

        foreach ($expiringEquipment as $equipment) {
            $vehicleCode = $equipment->vehicle?->internal_code ?? 'N/A';
            $title = "Attrezzatura in scadenza: {$equipment->name}";
            $message = "L'attrezzatura {$equipment->name} del veicolo {$vehicleCode} scade il {$equipment->expiration_date?->format('d/m/Y')}.";
            $url = $equipment->vehicle_id ? route('admin.vehicles.show', $equipment->vehicle_id) : null;

            if (!$this->alreadyNotified(Notification::TYPE_EQUIPMENT, $equipment->id)) {
                $notifications->notifyAdmins(Notification::TYPE_EQUIPMENT, $title, $message . " #equipment-{$equipment->id}", $url);
                $created++;
            }
        }

        $this->info("Generated {$created} notifications.");
        return Command::SUCCESS;
    }

    /**
     * Evita notifiche duplicate per la stessa risorsa.
     * Usa un marker nel messaggio per tracciare la risorsa notificata.
     */
    private function alreadyNotified(string $type, int $resourceId): bool
    {
        $marker = "#{$type}-{$resourceId}";
        return Notification::where('type', $type)
            ->where('message', 'like', "%{$marker}%")
            ->exists();
    }
}
