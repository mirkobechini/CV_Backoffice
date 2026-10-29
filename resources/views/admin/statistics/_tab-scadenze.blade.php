@php($maxTypeCount = $deadlineExpiredCountByType->max() ?: 1)

<div class="table-card" style="margin-bottom:16px;">
    <div class="toolbar">
        <h2>{{ __('Scadenze per stato (oggi)') }}</h2>
    </div>
    @if ($deadlineCountByStatus->isEmpty())
        <div class="body" style="padding:16px 20px;">
            <p class="hint" style="margin:0;">{{ __('Nessuna scadenza registrata.') }}</p>
        </div>
    @else
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Stato') }}</th>
                        <th>{{ __('Scadenze') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deadlineCountByStatus as $row)
                        <tr>
                            <td><x-admin.status-badge :color="$row['color']">{{ $row['label'] }}</x-admin.status-badge></td>
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
        <h2>{{ __('Scadenze scadute per tipo (oggi)') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @forelse ($deadlineExpiredCountByType as $type => $count)
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name">{{ $type }}</span>
                    <span class="meta" style="margin-left:auto;">{{ $count }}</span>
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--red);width:{{ ($count / $maxTypeCount) * 100 }}%;">
                    </div>
                </div>
            </div>
        @empty
            <p class="hint" style="margin:0;">{{ __('Nessuna scadenza scaduta al momento.') }}</p>
        @endforelse
    </div>
</div>

<div class="table-card">
    <div class="toolbar">
        <h2>{{ __('Costo medio rinnovo assicurazione') }}</h2>
    </div>
    <div class="body" style="padding:16px 20px;">
        @if ($averageInsuranceRenewalCost === null)
            <p class="hint" style="margin:0;">{{ __('Nessuna scadenza assicurazione con coperture registrate nel periodo selezionato.') }}</p>
        @else
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name">{{ __('Media') }}</span>
                    <span class="meta" style="margin-left:auto;">€ {{ number_format($averageInsuranceRenewalCost, 2, ',', '.') }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
