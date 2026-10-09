<?php

namespace App\Providers;

use App\Models\CarModel;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentIssue;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\EquipmentType;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Observers\DashboardCacheObserver;
use App\Observers\VehicleObserver;
use App\Support\UploadsDiskGuard;
use App\Policies\DeadlinePolicy;
use App\Policies\EquipmentIssuePolicy;
use App\Policies\EquipmentMaintenanceRecordPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\EquipmentTypePolicy;
use App\Policies\IssuePolicy;
use App\Policies\MaintenanceRecordPolicy;
use App\Policies\MileageLogPolicy;
use App\Policies\ProviderPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleTypePolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fail-fast se UPLOADS_DISK è assente/"public" in produzione (audit
        // sicurezza 2026-10-09): vedi UploadsDiskGuard.
        UploadsDiskGuard::assertSafeForEnvironment(app()->environment(), config('filesystems.uploads_disk'));

        // Usa template Bootstrap 5 per la paginazione (invece di Tailwind)
        Paginator::useBootstrapFive();
        // Rate limiting per le route admin (mutazioni)
        RateLimiter::for('admin-mutations', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiting per il login (già gestito da Breeze, ma rinforziamo)
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->input('email').'|'.$request->ip());
        });

        // Rate limiting per la verifica del codice 2FA al login: un
        // codice TOTP ha solo 10^6 combinazioni, senza un limite stretto
        // sarebbe forzabile a forza bruta nella finestra di validità.
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->session()->get('login.2fa_user_id') . '|' . $request->ip());
        });

        // Rate limiting per le route API protette
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiting per il webhook del bot Telegram (nessuna auth,
        // protetto dal secret header -- vedi TelegramWebhookController):
        // un budget largo ma non illimitato, per lo stesso motivo di
        // public-fleet-status qui sotto.
        RateLimiter::for('telegram-webhook', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Rate limiting per la pagina pubblica di stato flotta (nessuna auth,
        // protetta solo dal token nell'URL): limita i tentativi di indovinare
        // token validi a forza bruta.
        RateLimiter::for('public-fleet-status', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Rate limiting per la scansione del libretto: chiamata a un LLM
        // esterno, lenta e a pagamento, quindi un budget più stretto della
        // fascia generica admin-mutations.
        RateLimiter::for('vehicle-scan', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });
        // Registra le policy
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Provider::class, ProviderPolicy::class);
        Gate::policy(Issue::class, IssuePolicy::class);
        Gate::policy(MaintenanceRecord::class, MaintenanceRecordPolicy::class);
        Gate::policy(Deadline::class, DeadlinePolicy::class);
        Gate::policy(MileageLog::class, MileageLogPolicy::class);
        Gate::policy(Equipment::class, EquipmentPolicy::class);
        Gate::policy(EquipmentType::class, EquipmentTypePolicy::class);
        Gate::policy(EquipmentIssue::class, EquipmentIssuePolicy::class);
        Gate::policy(EquipmentMaintenanceRecord::class, EquipmentMaintenanceRecordPolicy::class);
        Gate::policy(VehicleType::class, VehicleTypePolicy::class);

        Vehicle::observe(VehicleObserver::class);

        // Invalida la cache dashboard (vedi DashboardCache/DashboardCacheObserver)
        // per ogni modello i cui dati vi compaiono dentro.
        Vehicle::observe(DashboardCacheObserver::class);
        Deadline::observe(DashboardCacheObserver::class);
        Issue::observe(DashboardCacheObserver::class);
        MaintenanceRecord::observe(DashboardCacheObserver::class);
        Equipment::observe(DashboardCacheObserver::class);
        Tire::observe(DashboardCacheObserver::class);
        MileageLog::observe(DashboardCacheObserver::class);

        Validator::extend('car_model_belongs_to_brand', function ($attribute, $value, $parameters, $validator) {
            $brandField = $parameters[0] ?? null;
            $brandId = data_get($validator->getData(), $brandField);

            if (! $brandId || ! $value) {
                return true;
            }

            return CarModel::where('id', $value)
                ->where('brand_id', $brandId)
                ->exists();
        });
    }
}
