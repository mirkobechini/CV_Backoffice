@php($maxKm = $kmTraveledByVehicle->max('km') ?: 1)

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Km percorsi per veicolo') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        <p class="hint" style="margin:0 0 10px;">
            {{ $period === 'all' ? __('Chilometraggio attuale (ultima lettura registrata).') : __('Distanza percorsa nel periodo selezionato.') }}
        </p>
        @forelse ($kmTraveledByVehicle as $row)
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name">{{ $row['label'] }}</span>
                    <span class="meta" style="margin-left:auto;">{{ number_format($row['km'], 0, ',', '.') }} km</span>
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--blue);width:{{ ($row['km'] / $maxKm) * 100 }}%;">
                    </div>
                </div>
            </div>
        @empty
            <p class="hint" style="margin:0;">
                {{ $period === 'all' ? __('Nessun veicolo con una lettura km registrata.') : __('Nessun veicolo con almeno due letture km nel periodo selezionato.') }}
            </p>
        @endforelse
    </div>
</div>

@php($maxIssueCount = $issueCountByVehicle->max('count') ?: 1)

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Veicoli con più guasti') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @forelse ($issueCountByVehicle as $row)
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name">{{ $row['label'] }}</span>
                    <span class="meta" style="margin-left:auto;">{{ $row['count'] }}</span>
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--amber);width:{{ ($row['count'] / $maxIssueCount) * 100 }}%;">
                    </div>
                </div>
            </div>
        @empty
            <p class="hint" style="margin:0;">{{ __('Nessun guasto nel periodo selezionato.') }}</p>
        @endforelse
    </div>
</div>

<div class="table-card">
    <div class="toolbar">
        <h2>{{ __('Tempo medio di fermo officina') }}</h2>
    </div>
    @if ($downtime['overall'] === null)
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun intervento completato nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="body" style="padding:16px 20px;">
            <div class="dash-list-item" style="align-items:baseline;">
                <span class="name">{{ __('Media complessiva') }}</span>
                <span class="meta" style="margin-left:auto;font-weight:700;">{{ __(':days giorni', ['days' => $downtime['overall']]) }}</span>
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Officina') }}</th>
                        <th>{{ __('Giorni medi') }}</th>
                        <th>{{ __('Interventi') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($downtime['byProvider'] as $providerLabel => $data)
                        <tr>
                            <td>{{ $providerLabel }}</td>
                            <td>{{ $data['avg_days'] }}</td>
                            <td>{{ $data['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
