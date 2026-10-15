@php($maxYearCost = $costByYear->max() ?: 1)

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Spesa per anno') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @forelse ($costByYear as $year => $cost)
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name">{{ $year }}</span>
                    <span class="meta" style="margin-left:auto;">€ {{ number_format($cost, 2, ',', '.') }}</span>
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--blue);width:{{ ($cost / $maxYearCost) * 100 }}%;">
                    </div>
                </div>
            </div>
        @empty
            <p class="hint" style="margin:0;">{{ __('Nessuna spesa registrata nel periodo selezionato.') }}</p>
        @endforelse
    </div>
</div>

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Spesa per veicolo') }}</h2>
    </div>
    @if ($costByVehicle->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessuna spesa registrata nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Spesa totale') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($costByVehicle as $row)
                        <tr>
                            <td class="code">{{ $row['label'] }}</td>
                            <td>€ {{ number_format($row['cost'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Spesa per officina') }}</h2>
    </div>
    @if ($costByProvider->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessuna spesa registrata nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Officina') }}</th>
                        <th>{{ __('Spesa totale') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($costByProvider as $providerLabel => $cost)
                        <tr>
                            <td>{{ $providerLabel }}</td>
                            <td>€ {{ number_format($cost, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="table-card">
    <div class="toolbar">
        <h2>{{ __('Costo per km percorso') }}</h2>
    </div>
    @if ($costPerKm->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun veicolo con dati sufficienti (spesa e km percorsi) nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('€/km') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($costPerKm as $row)
                        <tr>
                            <td class="code">{{ $row['label'] }}</td>
                            <td>€ {{ number_format($row['per_km'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="hint" style="padding:8px 20px 16px;">
            {{ __('Escluso chi ha percorso meno di :km km nel periodo: con pochi km anche una spesa piccola darebbe un €/km fuorviante.', ['km' => \App\Services\CostStatisticsService::MIN_KM_FOR_COST_PER_KM]) }}
        </div>
    @endif
</div>
