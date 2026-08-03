<?php

namespace App\Providers;

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\MileageLog;
use App\Models\Provider;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Observers\VehicleObserver;
use App\Policies\DeadlinePolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\EquipmentTypePolicy;
use App\Policies\IssuePolicy;
use App\Policies\MaintenanceRecordPolicy;
use App\Policies\MileageLogPolicy;
use App\Policies\ProviderPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\VehicleTypePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use App\Models\CarModel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\Paginator;

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
                ->by($request->input('email') . '|' . $request->ip());
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
        Gate::policy(VehicleType::class, VehicleTypePolicy::class);

        Vehicle::observe(VehicleObserver::class);
        Validator::extend('car_model_belongs_to_brand', function ($attribute, $value, $parameters, $validator) {
            $brandField = $parameters[0] ?? null;
            $brandId = data_get($validator->getData(), $brandField);

            if (!$brandId || !$value) {
                return true;
            }

            return CarModel::where('id', $value)
                ->where('brand_id', $brandId)
                ->exists();
        });
    }
}
