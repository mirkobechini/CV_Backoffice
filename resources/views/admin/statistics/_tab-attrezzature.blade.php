@php($maxIssueCount = $equipmentIssueCountByEquipment->max('count') ?: 1)
@php($maxTypeCount = $equipmentIssueCountByType->max() ?: 1)
@php($maxYearCost = $equipmentCostByYear->max() ?: 1)

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Attrezzature più problematiche') }}</h2>
    </div>
    @if ($equipmentIssueCountByEquipment->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessun guasto registrato nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Attrezzatura') }}</th>
                        <th>{{ __('Guasti') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($equipmentIssueCountByEquipment as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Guasti per tipo di attrezzatura') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @forelse ($equipmentIssueCountByType as $type => $count)
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name">{{ $type }}</span>
                    <span class="meta" style="margin-left:auto;">{{ $count }}</span>
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--blue);width:{{ ($count / $maxTypeCount) * 100 }}%;">
                    </div>
                </div>
            </div>
        @empty
            <p class="hint" style="margin:0;">{{ __('Nessun guasto registrato nel periodo selezionato.') }}</p>
        @endforelse
    </div>
</div>

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Spesa manutenzione attrezzature per anno') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @forelse ($equipmentCostByYear as $year => $cost)
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

<div class="table-card">
    <div class="toolbar">
        <h2>{{ __('Spesa manutenzione per attrezzatura') }}</h2>
    </div>
    @if ($equipmentCostByEquipment->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessuna spesa registrata nel periodo selezionato.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Attrezzatura') }}</th>
                        <th>{{ __('Spesa totale') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($equipmentCostByEquipment as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td>€ {{ number_format($row['cost'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
