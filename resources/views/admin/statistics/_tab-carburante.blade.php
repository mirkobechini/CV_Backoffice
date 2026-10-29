@php($maxYearLiters = $litersAndCostByYear->max('liters') ?: 1)

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Litri e spesa per anno') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @forelse ($litersAndCostByYear as $year => $row)
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name">{{ $year }}</span>
                    <span class="meta" style="margin-left:auto;">
                        {{ number_format($row['liters'], 2, ',', '.') }} L
                        · € {{ number_format($row['cost'], 2, ',', '.') }}
                    </span>
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--blue);width:{{ ($row['liters'] / $maxYearLiters) * 100 }}%;">
                    </div>
                </div>
            </div>
        @empty
            <p class="hint" style="margin:0;">{{ __('Nessun rifornimento registrato nel periodo selezionato.') }}</p>
        @endforelse
    </div>
</div>

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Litri e spesa per veicolo') }}</h2>
    </div>
    @if ($litersAndCostByVehicle->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun rifornimento registrato nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('Litri') }}</th>
                        <th>{{ __('Spesa totale') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($litersAndCostByVehicle as $row)
                        <tr>
                            <td class="code">{{ $row['label'] }}</td>
                            <td>{{ number_format($row['liters'], 2, ',', '.') }} L</td>
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
        <h2>{{ __('Litri e spesa per tipo di veicolo') }}</h2>
    </div>
    @if ($litersAndCostByVehicleType->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun rifornimento registrato nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Tipo veicolo') }}</th>
                        <th>{{ __('Litri') }}</th>
                        <th>{{ __('Spesa totale') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($litersAndCostByVehicleType as $type => $row)
                        <tr>
                            <td>{{ $type }}</td>
                            <td>{{ number_format($row['liters'], 2, ',', '.') }} L</td>
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
        <h2>{{ __('Litri e spesa per alimentazione') }}</h2>
    </div>
    @if ($litersAndCostByFuelType->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun rifornimento registrato nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Alimentazione') }}</th>
                        <th>{{ __('Litri') }}</th>
                        <th>{{ __('Spesa totale') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($litersAndCostByFuelType as $row)
                        <tr>
                            <td>
                                @if ($row['fuel_type'])
                                    <x-admin.status-badge
                                        :color="\App\Models\Vehicle::fuelTypeBadgeColors()[$row['fuel_type']] ?? 'gray'">
                                        {{ $row['label'] }}
                                    </x-admin.status-badge>
                                @else
                                    <x-admin.status-badge color="gray">{{ $row['label'] }}</x-admin.status-badge>
                                @endif
                            </td>
                            <td>{{ number_format($row['liters'], 2, ',', '.') }} L</td>
                            <td>€ {{ number_format($row['cost'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="table-card">
    <div class="toolbar">
        <h2>{{ __('Consumo medio per veicolo') }}</h2>
    </div>
    @if ($averageConsumptionByVehicle->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun veicolo con almeno due rifornimenti consecutivi con chilometraggio nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Veicolo') }}</th>
                        <th>{{ __('km/L') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($averageConsumptionByVehicle as $row)
                        <tr>
                            <td class="code">{{ $row['label'] }}</td>
                            <td>{{ number_format($row['km_per_liter'], 2, ',', '.') }} km/L</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
