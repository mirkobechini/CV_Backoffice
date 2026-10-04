@props(['vehicle'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Specifiche tecniche') }}</h3>
    </div>
    <div class="body">
        <div class="veh-kv"><span class="k">{{ __('Telaio (VIN)') }}</span><span
                class="v">{{ $vehicle->vin ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Colore') }}</span><span
                class="v">{{ $vehicle->color ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Numero posti') }}</span><span
                class="v">{{ $vehicle->seats ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Categoria veicolo') }}</span><span
                class="v">{{ $vehicle->vehicle_category ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Classe ambientale') }}</span><span
                class="v">{{ $vehicle->environmental_class ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Massa massima') }}</span><span
                class="v">{{ $vehicle->max_mass_kg ? number_format($vehicle->max_mass_kg, 0, ',', '.') . ' kg' : 'N/A' }}</span>
        </div>
        <div class="veh-kv"><span class="k">{{ __('Cilindrata') }}</span><span
                class="v">{{ $vehicle->engine_displacement_cc ? number_format($vehicle->engine_displacement_cc, 0, ',', '.') . ' cc' : 'N/A' }}</span>
        </div>
        <div class="veh-kv"><span class="k">{{ __('Potenza') }}</span><span
                class="v">{{ $vehicle->engine_power_kw ? $vehicle->engine_power_kw . ' kW' : 'N/A' }}</span>
        </div>
        <div class="veh-kv"><span class="k">{{ __('Misure pneumatici') }}</span><span
                class="v">{{ ! empty($vehicle->allowed_tire_sizes) ? implode(', ', $vehicle->allowed_tire_sizes) : 'N/A' }}</span>
        </div>
    </div>
</div>
