@props(['tireSeasonExpected', 'tireSeasonNextSwitch', 'tireSeasonCompliantCount', 'tireSeasonPending', 'tireSeasonOverdue'])

@php
    $tireSeasonLabel = $tireSeasonExpected === 'winter' ? __('invernali') : __('estive');
    $tireSeasonNextLabel = $tireSeasonNextSwitch['season'] === 'winter' ? __('invernali') : __('estive');
    $tireSeasonTotal = $tireSeasonCompliantCount + $tireSeasonPending->count();
    // Entro i 30 giorni di preavviso prima del cambio, chi non ha ancora
    // cambiato è segnalato in anticipo ma non è ancora davvero scaduto;
    // dopo la data di cambio reale lo è.
    $tireSeasonPendingBadge = $tireSeasonOverdue ? __('scaduto') : __('da cambiare');
    $tireSeasonPendingBadgeClass = $tireSeasonOverdue ? 'b-red' : 'b-amber';
@endphp
@if ($tireSeasonTotal > 0)
    <div class="dash-card">
        <div class="head">
            <h3><span class="ic" style="background:var(--green-soft);color:var(--green)"><i
                        class="fa-solid fa-circle-dot"></i></span>{{ __('Cambio gomme stagionale') }}</h3>
        </div>
        <div class="body">
            <div class="hint" style="margin-bottom:10px;">
                {{ __('In questo periodo vanno montate le gomme :season.', ['season' => $tireSeasonLabel]) }}
                {{ __('Prossimo cambio: gomme :season dal :date.', ['season' => $tireSeasonNextLabel, 'date' => $tireSeasonNextSwitch['date']->format('d/m')]) }}
            </div>
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:6px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name" style="font-size:17px;">{{ $tireSeasonCompliantCount }}</span>
                    <span class="meta">/ {{ $tireSeasonTotal }} {{ __('veicoli già cambiati') }}</span>
                    @if ($tireSeasonPending->isNotEmpty())
                        <span class="badge {{ $tireSeasonPendingBadgeClass }}" style="margin-left:auto;">
                            {{ $tireSeasonOverdue
                                ? trans_choice('{1} :count veicolo scaduto|[2,*] :count veicoli scaduti', $tireSeasonPending->count(), ['count' => $tireSeasonPending->count()])
                                : trans_choice('{1} :count veicolo da cambiare|[2,*] :count veicoli da cambiare', $tireSeasonPending->count(), ['count' => $tireSeasonPending->count()]) }}
                        </span>
                    @endif
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--green);width:{{ $tireSeasonTotal > 0 ? ($tireSeasonCompliantCount / $tireSeasonTotal) * 100 : 0 }}%;">
                    </div>
                </div>
            </div>
            @foreach ($tireSeasonPending as $vehicle)
                <a href="{{ route('admin.vehicles.show', $vehicle->id) }}" class="dash-list-item">
                    <div>
                        <div class="name">{{ $vehicle->internal_code }}</div>
                        <div class="meta">{{ $vehicle->license_plate }}</div>
                    </div>
                    <span class="badge {{ $tireSeasonPendingBadgeClass }}">{{ $tireSeasonPendingBadge }}</span>
                </a>
            @endforeach
            <a href="{{ route('admin.tires.index', ['season_filter' => 'due']) }}"
                class="dash-see-all">{{ __('Vedi pneumatici da cambiare') }} ›</a>
        </div>
    </div>
@endif
