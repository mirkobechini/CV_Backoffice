<?php

namespace App\Providers;

use App\Models\Vehicle;
use App\Observers\VehicleObserver;
use Illuminate\Support\ServiceProvider;

use App\Models\CarModel;
use Illuminate\Support\Facades\Validator;

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
