<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Models\Issue;
use App\Models\User;
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

        // Il report è per gruppo: ogni destinatario deve vedere solo i dati
        // del proprio gruppo, quindi qui sotto non si può più precalcolare
        // nulla in blocco per tutti i destinatari (comando da console, senza
        // Auth::user(): senza scoping esplicito per gruppo ogni query
        // tornerebbe i dati di TUTTI i gruppi, inviati via email a chiunque).

        // Frequenza e giorni di preavviso di tutti i destinatari in un'unica
        // query, invece di due query per utente dentro il ciclo sotto.
        $extraSettings = NotificationSetting::whereIn('user_id', $recipientRows->pluck('user_id'))
            ->whereIn('key', ['report_frequency', 'reminder_days_before'])
            ->get(['user_id', 'key', 'value'])
            ->groupBy('user_id');

        $groupIdByUserId = User::whereIn('id', $recipientRows->pluck('user_id'))
            ->with('groups')
            ->get()
            ->mapWithKeys(fn (User $user) => [$user->id => $user->activeGroup()?->id]);

        $today = Carbon::today();
        $sentCount = 0;

        // Parti del report indipendenti dai giorni di preavviso: calcolate
        // una sola volta per gruppo (e riutilizzate se più destinatari
        // condividono lo stesso gruppo), non più per tutti i destinatari
        // indistintamente.
        $perGroupData = [];

        foreach ($recipientRows as $row) {
            $userId = $row->user_id;
            $userSettings = $extraSettings->get($userId, collect());
            $frequency = $userSettings->firstWhere('key', 'report_frequency')?->value ?? 'daily';

            $isSendDay = match ($frequency) {
                'weekly' => $today->isMonday(),
                'monthly' => $today->day === 1,
                default => true, // daily
            };

            if (! $isSendDay) {
                continue;
            }

            $reminderDays = (int) ($userSettings->firstWhere('key', 'reminder_days_before')?->value ?? 7);
            $groupId = $groupIdByUserId->get($userId);

            // 'reminder_days_before' cambia i dati per-utente anche a parità
            // di gruppo, quindi la cache è per gruppo + giorni di preavviso.
            $cacheKey = ($groupId ?? 'none') . ':' . $reminderDays;

            if (! isset($perGroupData[$cacheKey])) {
                $allVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')
                    ->forGroup($groupId)
                    ->get();

                $vehicleIdsWithOpenIssues = Issue::open()
                    ->whereHas('vehicle', fn ($q) => $q->forGroup($groupId))
                    ->distinct('vehicle_id')
                    ->pluck('vehicle_id');

                $perGroupData[$cacheKey] = [
                    'totalVehicles' => $allVehicles->count(),
                    'vehiclesOk' => $allVehicles->reject(fn ($v) => $vehicleIdsWithOpenIssues->contains($v->id))->count(),
                    'openIssues' => Issue::with('vehicle')->whereHas('vehicle', fn ($q) => $q->forGroup($groupId))->open()->get(),
                    // Filtriamo su automatic_status (calcolato live da data/km), non
                    // sulla colonna status persistita: quest'ultima viene
                    // risincronizzata solo alla creazione/modifica di una scadenza o
                    // visitando l'elenco scadenze (Deadline::syncStatusesFromRules())
                    // — una scadenza il cui due_date passa senza che nessuno la tocchi
                    // resta "in regola" in DB anche se ormai scaduta, e il report
                    // giornaliero non la includeva mai (stesso bug già corretto sulla
                    // dashboard, vedi DashboardController).
                    'expiredDeadlines' => Deadline::with('vehicle.latestMileageLog')
                        ->whereHas('vehicle', fn ($q) => $q->forGroup($groupId))
                        ->where('is_renewed', false)
                        ->get()
                        ->filter(fn (Deadline $d) => $d->automatic_status === Deadline::STATUS_EXPIRED)
                        ->sortBy('due_date')
                        ->values(),
                    'upcomingDeadlines' => Deadline::with('vehicle')
                        ->whereHas('vehicle', fn ($q) => $q->forGroup($groupId))
                        ->upcoming($reminderDays)
                        ->get(),
                    'upcomingAppointments' => MaintenanceRecord::with('vehicle', 'provider', 'items.itemable')
                        ->whereHas('vehicle', fn ($q) => $q->forGroup($groupId))
                        ->whereNull('return_date')
                        ->where('appointment_date', '>=', today())
                        ->orderBy('appointment_date')
                        ->take(5)
                        ->get(),
                    'incompleteVehicles' => $allVehicles->filter(fn ($v) => ! $v->hasAllRequiredEquipment()),
                    'vehiclesInMaintenance' => MaintenanceRecord::whereHas('vehicle', fn ($q) => $q->forGroup($groupId))
                        ->whereNull('return_date')
                        ->distinct('vehicle_id')
                        ->count('vehicle_id'),
                    // L'attrezzatura non assegnata a un veicolo non ha un
                    // gruppo proprio: resta inclusa, come nell'indice
                    // attrezzature.
                    'expiringEquipment' => Equipment::with('vehicle')
                        ->where(function ($q) use ($groupId) {
                            $q->whereDoesntHave('vehicle')
                                ->orWhereHas('vehicle', fn ($vq) => $vq->forGroup($groupId));
                        })
                        ->expiringSoon($reminderDays)
                        ->get(),
                ];
            }

            Mail::to($row->value)->send(new ReportMail($perGroupData[$cacheKey]));
            $this->info("Report inviato con successo a {$row->value}!");
            $sentCount++;
        }

        if ($sentCount === 0) {
            $this->info('Nessun report da inviare oggi.');
        }

        return Command::SUCCESS;
    }
}
