@props(['reliabilityTrend'])

@if ($reliabilityTrend)
    <div class="veh-card">
        <div class="head">
            <h3>{{ __('Tendenza riparazioni') }}</h3>
        </div>
        <div class="body">
            @if ($reliabilityTrend['is_worsening'])
                <div class="veh-dl-item">
                    <span class="dot-type" style="background:var(--red);"></span>
                    <div style="min-width:0;">
                        <div class="name">{{ __('In peggioramento') }}</div>
                        <div class="date">
                            {{ __('Ultimo intervallo tra riparazioni: :days giorni, contro una media precedente più lunga.', ['days' => $reliabilityTrend['last_interval_days']]) }}
                        </div>
                    </div>
                </div>
            @endif
            <div class="veh-kv">
                <span class="k">{{ __('Riparazioni registrate') }}</span>
                <span class="v">{{ $reliabilityTrend['repairs_count'] }}</span>
            </div>
            <div class="veh-kv">
                <span class="k">{{ __('Intervallo medio') }}</span>
                <span class="v">{{ $reliabilityTrend['average_interval_days'] }} {{ __('giorni') }}</span>
            </div>
            <div class="veh-kv">
                <span class="k">{{ __('Ultimo intervallo') }}</span>
                <span class="v">{{ $reliabilityTrend['last_interval_days'] }} {{ __('giorni') }}</span>
            </div>
            <div class="hint" style="margin-top:8px;">
                {{ __('Basato sulle riparazioni non programmate: non è una previsione di quando avverrà il prossimo guasto, ma un confronto con il ritmo storico di questo veicolo.') }}
            </div>
        </div>
    </div>
@endif
