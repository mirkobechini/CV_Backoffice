@props(['vehicle'])

{{-- Vista compatta sola lettura per la tab Panoramica (restyle "Saru",
     ADR §19): la card interattiva completa resta nella tab Pneumatici. --}}
@php($mountedTires = $vehicle->tires->where('status', 'mounted'))

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Pneumatici') }}</h3>
    </div>
    <div class="body">
        @if ($mountedTires->isEmpty())
            <div class="veh-eq-item">
                <div>
                    <div class="name">{{ __('Nessun set di gomme montato') }}</div>
                </div>
            </div>
        @else
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                @foreach ($mountedTires as $tire)
                    <div style="background:var(--surface-2); border-radius:var(--radius); padding:10px 12px;">
                        <div style="font-size:11px; color:var(--text-muted); font-weight:600;">
                            {{ $tire->position_label }}</div>
                        <div style="font-size:13px; font-weight:700;">{{ $tire->size ?? 'N/A' }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
