@props(['vehicle'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Anagrafica') }}</h3>
    </div>
    <div class="body">
        <div class="veh-kv"><span class="k">{{ __('Targa') }}</span><span
                class="v">{{ $vehicle->license_plate ?? '—' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Marca') }}</span><span
                class="v">{{ $vehicle->brand->name ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Modello') }}</span><span
                class="v">{{ $vehicle->carModel->name ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Carburante') }}</span><span
                class="v">{{ $vehicle->fuel_type ?? '—' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Tipo') }}</span><span
                class="v">{{ $vehicle->vehicleType->name ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Distribuzione') }}</span><span class="v">
                {{ $vehicle->timing_belt_type_label }}
            </span>
        </div>
    </div>
</div>
