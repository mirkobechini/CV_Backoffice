<?php

namespace App\Console\Commands;

use App\Mail\EventNotificationMail;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateNotifications extends Command
{
    // php artisan app:generate-notifications [--email]
    protected $signature = 'app:generate-notifications {--email : Invia anche email automatiche per i nuovi eventi}';

    protected $description = 'Genera notifiche in-app per scadenze, guasti, attrezzature e appuntamenti in scadenza';

    /**
     * Le impostazioni di notifica (frequenza, promemoria, tipi di evento) sono
     * personali per account: ogni utente admin/sottocapo viene valutato con
     * le proprie preferenze, non con un'unica configurazione globale.
     */
    public function handle(NotificationService $notifications)
    {
        $created = 0;
        $emailsByUser = [];

        $adminUsers = User::whereHas('groups', function ($q) {
            $q->whereIn('group_user.role', [Group::ROLE_CAPO, Group::ROLE_SOTTOCAPO]);
        })->get();

        foreach ($adminUsers as $user) {
            $reminderDays = (int) $user->notificationSetting('reminder_days_before', 7);

            if ($user->notificationSetting('notify_on_deadline', true)) {
                $this->notifyUpcomingDeadlines($user, $reminderDays, $notifications, $emailsByUser, $created);
            }

            if ($user->notificationSetting('notify_on_issue', true)) {
                $this->notifyOpenIssues($user, $notifications, $emailsByUser, $created);
            }

            if ($user->notificationSetting('notify_on_equipment', true)) {
                $this->notifyExpiringEquipment($user, $reminderDays, $notifications, $emailsByUser, $created);
            }

            if ($user->notificationSetting('notify_on_maintenance', true)) {
                $this->notifyUpcomingAppointments($user, $reminderDays, $notifications, $emailsByUser, $created);
            }
        }

        // Invia email automatiche per i nuovi eventi, una per utente e per evento.
        if ($this->option('email')) {
            foreach ($emailsByUser as $userId => $mails) {
                $recipient = NotificationSetting::where('user_id', $userId)->where('key', 'report_email')->value('value');

                if (! $recipient) {
                    continue;
                }

                foreach ($mails as $mail) {
                    Mail::to($recipient)->send($mail);
                }
                $this->info('Sent ' . count($mails) . " email(s) to {$recipient}.");
            }
        }

        $this->info("Generated {$created} notifications.");

        return Command::SUCCESS;
    }

    private function notifyUpcomingDeadlines(User $user, int $reminderDays, NotificationService $notifications, array &$emailsByUser, int &$created): void
    {
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
            $marker = "#deadline-{$deadline->id}";

            if ($this->alreadyNotified($user->id, Notification::TYPE_DEADLINE, $marker)) {
                continue;
            }

            $notifications->notifyUser($user, Notification::TYPE_DEADLINE, $title, "{$message} {$marker}", $url);
            $emailsByUser[$user->id][] = new EventNotificationMail(Notification::TYPE_DEADLINE, $title, $message, $url);
            $created++;
        }
    }

    private function notifyOpenIssues(User $user, NotificationService $notifications, array &$emailsByUser, int &$created): void
    {
        $openIssues = Issue::with('vehicle')->open()->get();

        foreach ($openIssues as $issue) {
            $vehicleCode = $issue->vehicle?->internal_code ?? 'N/A';
            $title = "Guasto aperto: {$vehicleCode}";
            $message = $issue->description;
            $url = $issue->vehicle_id ? route('admin.vehicles.show', $issue->vehicle_id) : null;
            $marker = "#issue-{$issue->id}";

            if ($this->alreadyNotified($user->id, Notification::TYPE_ISSUE, $marker)) {
                continue;
            }

            $notifications->notifyUser($user, Notification::TYPE_ISSUE, $title, "{$message} {$marker}", $url);
            $emailsByUser[$user->id][] = new EventNotificationMail(Notification::TYPE_ISSUE, $title, $message, $url);
            $created++;
        }
    }

    private function notifyExpiringEquipment(User $user, int $reminderDays, NotificationService $notifications, array &$emailsByUser, int &$created): void
    {
        $expiringEquipment = Equipment::with('vehicle')->expiringSoon($reminderDays)->get();

        foreach ($expiringEquipment as $equipment) {
            $vehicleCode = $equipment->vehicle?->internal_code ?? 'N/A';
            $title = "Attrezzatura in scadenza: {$equipment->name}";
            $message = "L'attrezzatura {$equipment->name} del veicolo {$vehicleCode} scade il {$equipment->expiration_date?->format('d/m/Y')}.";
            $url = $equipment->vehicle_id ? route('admin.vehicles.show', $equipment->vehicle_id) : null;
            $marker = "#equipment-{$equipment->id}";

            if ($this->alreadyNotified($user->id, Notification::TYPE_EQUIPMENT, $marker)) {
                continue;
            }

            $notifications->notifyUser($user, Notification::TYPE_EQUIPMENT, $title, "{$message} {$marker}", $url);
            $emailsByUser[$user->id][] = new EventNotificationMail(Notification::TYPE_EQUIPMENT, $title, $message, $url);
            $created++;
        }
    }

    /**
     * Appuntamenti (officina) in arrivo e non ancora conclusi.
     */
    private function notifyUpcomingAppointments(User $user, int $reminderDays, NotificationService $notifications, array &$emailsByUser, int &$created): void
    {
        $upcomingAppointments = MaintenanceRecord::with('vehicle')
            ->whereNull('return_date')
            ->whereNotNull('appointment_date')
            ->whereBetween('appointment_date', [today(), today()->addDays($reminderDays)])
            ->get();

        foreach ($upcomingAppointments as $record) {
            $vehicleCode = $record->vehicle?->internal_code ?? 'N/A';
            $title = "Appuntamento in arrivo: {$vehicleCode}";
            $activitySuffix = $record->activity_type ? " ({$record->activity_type})" : '';
            $message = "Il veicolo {$vehicleCode} ha un appuntamento{$activitySuffix} il {$record->appointment_date?->format('d/m/Y')}.";
            $url = $record->vehicle_id ? route('admin.vehicles.show', $record->vehicle_id) : null;
            $marker = "#maintenance-{$record->id}";

            if ($this->alreadyNotified($user->id, Notification::TYPE_MAINTENANCE, $marker)) {
                continue;
            }

            $notifications->notifyUser($user, Notification::TYPE_MAINTENANCE, $title, "{$message} {$marker}", $url);
            $emailsByUser[$user->id][] = new EventNotificationMail(Notification::TYPE_MAINTENANCE, $title, $message, $url);
            $created++;
        }
    }

    /**
     * Evita notifiche duplicate per la stessa risorsa e lo stesso utente.
     * Usa un marker nel messaggio per tracciare la risorsa notificata.
     */
    private function alreadyNotified(int $userId, string $type, string $marker): bool
    {
        return Notification::where('user_id', $userId)
            ->where('type', $type)
            ->where('message', 'like', "%{$marker}%")
            ->exists();
    }
}
