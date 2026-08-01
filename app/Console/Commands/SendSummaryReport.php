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
     */
    public function handle()
    {
        $totalVehicles = Vehicle::count();

        // Carichiamo tutti i veicoli con le relazioni necessarie in una sola query
        $allVehicles = Vehicle::with('vehicleType.equipmentTypes', 'equipment')->get();

        // Veicoli senza guasti aperti/in lavorazione (ok)
        $vehicleIdsWithOpenIssues = Issue::whereIn('status', ['open', 'in_progress'])
            ->distinct('vehicle_id')
            ->pluck('vehicle_id');
        $vehiclesOk = $allVehicles->reject(fn($v) => $vehicleIdsWithOpenIssues->contains($v->id))->count();

        // Veicoli con equipaggiamento incompleto (dalla collection già caricata)
        $incompleteVehicles = $allVehicles->filter(fn($v) => !$v->hasAllRequiredEquipment());

        $openIssues = Issue::with('vehicle')->whereIn('status', ['open', 'in_progress'])->get();
        $expiredDeadlines = Deadline::where('status', 'expired')->where('is_renewed', false)->get();
        $upcomingDeadlines = Deadline::with('vehicle')
            ->where('due_date', '>=', Carbon::today())
            ->where('due_date', '<=', Carbon::today()->addDays(30))
            ->orderBy('due_date')
            ->get();
        $upcomingAppointments = MaintenanceRecord::with('vehicle', 'provider', 'items.itemable')->whereNull('return_date')->where('appointment_date', '>=', today())->orderBy('appointment_date')->take(5)->get();
        $vehiclesInMaintenance = MaintenanceRecord::whereNull('return_date')
            ->distinct('vehicle_id')
            ->count('vehicle_id');
        $expiringEquipment = Equipment::with('vehicle')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::today()->addDays(30))
            ->orderBy('expiration_date')
            ->get();

        $data = [
            'totalVehicles' => $totalVehicles,
            'vehiclesOk' => $vehiclesOk,
            'openIssues' => $openIssues,
            'expiredDeadlines' => $expiredDeadlines,
            'upcomingDeadlines' => $upcomingDeadlines,
            'upcomingAppointments' => $upcomingAppointments,
            'incompleteVehicles' => $incompleteVehicles,
            'vehiclesInMaintenance' => $vehiclesInMaintenance,
            'expiringEquipment' => $expiringEquipment,
        ];
        Mail::to('test@example.com')->send(new ReportMail($data));
        $this->info('Report inviato con successo!');
    }
}
