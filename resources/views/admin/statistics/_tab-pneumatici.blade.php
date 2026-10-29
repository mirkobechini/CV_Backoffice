@php($maxYearCost = $tireCostByYear->max() ?: 1)

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Spesa cambio gomme per anno') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @forelse ($tireCostByYear as $year => $cost)
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
            <p class="hint" style="margin:0;">{{ __('Nessuna spesa cambio gomme registrata nel periodo selezionato.') }}</p>
        @endforelse
    </div>
</div>

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Spesa cambio gomme per veicolo') }}</h2>
    </div>
    @if ($tireCostByVehicle->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessuna spesa cambio gomme registrata nel periodo selezionato.') }}</p>
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
                    @foreach ($tireCostByVehicle as $row)
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
        <h2>{{ __('Numero cambi gomme per veicolo') }}</h2>
    </div>
    @if ($tireChangeFrequencyByVehicle->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun cambio gomme registrato nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Cambi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tireChangeFrequencyByVehicle as $row)
                        <tr>
                            <td class="code">{{ $row['label'] }}</td>
                            <td>{{ $row['changes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="table-card">
    <div class="toolbar">
        <h2>{{ __('Km medi tra due cambi') }}</h2>
    </div>
    @if ($averageKmBetweenChangesByVehicle->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun veicolo con almeno due cambi gomme consecutivi con chilometraggio nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Km medi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($averageKmBetweenChangesByVehicle as $row)
                        <tr>
                            <td class="code">{{ $row['label'] }}</td>
                            <td>{{ number_format($row['avg_km'], 0, ',', '.') }} km</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
