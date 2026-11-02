@props(['carWashTotal', 'carWashWashedCount', 'carWashMissingVehicles', 'carWashRecords'])

@if ($carWashTotal > 0)
    <div class="dash-card">
        <div class="head">
            <h3><span class="ic" style="background:var(--blue-soft);color:var(--blue)"><i
                        class="fa-solid fa-soap"></i></span>{{ __('Lavaggi del mese') }}</h3>
        </div>
        <div class="body">
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:6px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name" style="font-size:17px;">{{ $carWashWashedCount }}</span>
                    <span class="meta">/ {{ $carWashTotal }} {{ __('veicoli lavati questo mese') }}</span>
                    @if ($carWashMissingVehicles->isNotEmpty())
                        <span class="badge b-amber" style="margin-left:auto;">
                            {{ trans_choice('{1} :count veicolo da lavare|[2,*] :count veicoli da lavare', $carWashMissingVehicles->count(), ['count' => $carWashMissingVehicles->count()]) }}
                        </span>
                    @endif
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--blue);width:{{ $carWashTotal > 0 ? ($carWashWashedCount / $carWashTotal) * 100 : 0 }}%;">
                    </div>
                </div>
            </div>
            <details>
                <summary style="cursor:pointer;color:var(--text-muted);font-size:13px;padding:8px 0;">
                    {{ __('Vedi dettaglio') }}</summary>
                {{-- Scroll interno invece di allungare la card (e la colonna
                     intera, essendo l'ultima — vedi .dash-col >
                     .dash-card:last-child) quando l'elenco è lungo. --}}
                <div style="max-height:220px; overflow-y:auto;">
                    @if ($carWashMissingVehicles->isNotEmpty())
                        <div class="meta" style="margin:8px 0 4px;">{{ __('Da lavare') }}</div>
                        @foreach ($carWashMissingVehicles as $vehicle)
                            <a href="{{ route('admin.vehicles.show', $vehicle->id) }}" class="dash-list-item">
                                <div>
                                    <div class="name">{{ $vehicle->internal_code }}</div>
                                    <div class="meta">{{ $vehicle->license_plate }}</div>
                                </div>
                                <span class="badge b-amber">{{ __('da lavare') }}</span>
                            </a>
                        @endforeach
                    @endif
                    @if ($carWashRecords->isNotEmpty())
                        <div class="meta" style="margin:8px 0 4px;">{{ __('Già lavati') }}</div>
                        @foreach ($carWashRecords as $record)
                            <a href="{{ route('admin.vehicles.show', $record->vehicle_id) }}" class="dash-list-item">
                                <div>
                                    <div class="name">{{ $record->vehicle->internal_code }}</div>
                                    <div class="meta">{{ $record->vehicle->license_plate }}</div>
                                </div>
                                <span class="meta">{{ $record->return_date->format('d/m/Y') }}</span>
                            </a>
                        @endforeach
                    @endif
                </div>
            </details>
        </div>
    </div>
@endif
