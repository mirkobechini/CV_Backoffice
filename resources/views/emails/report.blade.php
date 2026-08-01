<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background-color: #f5f7fa;
        margin: 0;
        padding: 0;
    }

    .container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        color: white;
        padding: 24px 20px;
        border-radius: 12px 12px 0 0;
        text-align: center;
    }

    .header h1 {
        margin: 0;
        font-size: 22px;
    }

    .header p {
        margin: 4px 0 0;
        opacity: 0.9;
        font-size: 14px;
    }

    .body {
        background: #ffffff;
        padding: 20px;
        border-radius: 0 0 12px 12px;
    }

    .stats-row {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }

    .stat-box {
        flex: 1;
        min-width: 120px;
        padding: 12px;
        border-radius: 10px;
        text-align: center;
        font-size: 13px;
    }

    .stat-box .number {
        font-size: 24px;
        font-weight: bold;
        display: block;
    }

    .stat-green {
        background: #d4edda;
        color: #155724;
    }

    .stat-red {
        background: #f8d7da;
        color: #721c24;
    }

    .stat-yellow {
        background: #fff3cd;
        color: #856404;
    }

    .stat-blue {
        background: #cce5ff;
        color: #004085;
    }

    .section {
        margin-bottom: 20px;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        overflow: hidden;
    }

    .section-header {
        padding: 10px 16px;
        font-weight: 600;
        font-size: 14px;
        border-bottom: 1px solid #e9ecef;
    }

    .section-header.red {
        background: #fef2f2;
        color: #dc2626;
    }

    .section-header.yellow {
        background: #fffbeb;
        color: #d97706;
    }

    .section-header.blue {
        background: #eff6ff;
        color: #2563eb;
    }

    .section-header.gray {
        background: #f9fafb;
        color: #374151;
    }

    .section-item {
        padding: 10px 16px;
        border-bottom: 1px solid #f3f4f6;
        font-size: 13px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .section-item:last-child {
        border-bottom: none;
    }

    .section-item .label {
        color: #374151;
    }

    .section-item .meta {
        color: #6b7280;
        font-size: 12px;
    }

    .badge {
        font-size: 11px;
        padding: 2px 8px;
        border-radius: 6px;
        font-weight: 600;
    }

    .badge-red {
        background: #fef2f2;
        color: #dc2626;
    }

    .badge-yellow {
        background: #fffbeb;
        color: #d97706;
    }

    .badge-green {
        background: #dcfce7;
        color: #16a34a;
    }

    .badge-blue {
        background: #eff6ff;
        color: #2563eb;
    }

    .footer {
        text-align: center;
        padding: 16px;
        font-size: 12px;
        color: #9ca3af;
    }

    .progress-bar {
        height: 10px;
        background: #e9ecef;
        border-radius: 5px;
        margin: 6px 0;
        overflow: hidden;
    }

    .progress-bar .fill {
        height: 100%;
        border-radius: 5px;
        background: #22c55e;
    }

    .empty-state {
        padding: 16px;
        text-align: center;
        color: #9ca3af;
        font-size: 13px;
    }
</style>

<div class="container">
    <!-- HEADER -->
    <div class="header">
        <h1>📋 Report giornaliero</h1>
        <p>30 luglio 2026</p>
    </div>

    <div class="body">

        <!-- VEICOLI OK -->
        <div style="text-align:center; margin-bottom: 20px;">
            <div style="color:#6b7280; font-size:13px;">Veicoli a posto</div>
            <div style="font-size:28px; font-weight:bold; color:#16a34a;">
                {{ $data['vehiclesOk'] }}/{{ $data['totalVehicles'] }}</div>
            <div class="progress-bar" style="max-width:300px; margin: 8px auto;">
                <div class="fill" style="width:{{ ($data['vehiclesOk'] / $data['totalVehicles']) * 100 }}%"></div>
            </div>
        </div>

        <!-- STATISTICHE RAPIDE -->
        <div class="stats-row">
            <div class="stat-box stat-red">
                <span class="number">{{ $data['openIssues']->count() }}</span>
                Guasti aperti
            </div>
            <div class="stat-box stat-yellow">
                <span
                    class="number">{{ $data['expiredDeadlines']->count() + $data['upcomingDeadlines']->count() }}</span>
                Scadenze in arrivo
            </div>
            <div class="stat-box stat-blue">
                <span class="number">{{ $data['vehiclesInMaintenance'] }}</span>
                In officina
            </div>
        </div>

        <!-- SCADENZE -->
        <div class="section">
            <div class="section-header gray">📅 Scadenze</div>
            @if ($data['expiredDeadlines']->isNotEmpty())
                <!-- SCADENZE DA RINNOVARE -->
                <div class="section-header red">🔴 Da rinnovare</div>
                @foreach ($data['expiredDeadlines'] as $deadline)
                    <div class="section-item">
                        <div>
                            <div class="label">{{ $deadline->type }}</div>
                            <div class="meta">{{ $deadline->vehicle->internal_code }} — Scadeva il
                                {{ $deadline->due_date->format('d/m/Y') }}</div>
                        </div>
                        <span class="badge badge-red">Scaduta</span>
                    </div>
                @endforeach
            @endif

            <!-- SCADENZE IN ARRIVO -->
            @if ($data['upcomingDeadlines']->isNotEmpty())
                <div class="section-header yellow">🟡 In arrivo</div>
                @foreach ($data['upcomingDeadlines'] as $deadline)
                    <div class="section-item">
                        <div>
                            <div class="label">{{ $deadline->type }}</div>
                            <div class="meta">{{ $deadline->vehicle->internal_code }} — Tra
                                {{ $deadline->due_date->diffInDays(now()) }} giorni
                                ({{ $deadline->due_date->format('d/m') }})
                            </div>
                        </div>
                        <span class="badge badge-yellow">Imminente</span>
                    </div>
                @endforeach
            @endif
        </div>

        <!-- GUASTI APERTI -->
        <div class="section">
            <div class="section-header red">🔧 Guasti aperti</div>
            @if ($data['openIssues']->isEmpty())
                <div class="empty-state">
                    <span>Nessun guasto aperto ✅</span>
                </div>
            @else
                @foreach ($data['openIssues'] as $issue)
                    <div class="section-item">
                        <div>
                            <div class="label">{{ $issue->description }}</div>
                            <div class="meta">{{ $issue->vehicle->internal_code }} —
                                {{ $issue->event_date->format('d/m/Y') }}</div>
                        </div>
                        <span class="badge badge-{{ $issue->status_color }}">{{ $issue->status_label }}</span>
                    </div>
                @endforeach
            @endif
        </div>

        <!-- APPUNTAMENTI IN OFFICINA -->
        <div class="section">
            <div class="section-header blue">🏪 In officina</div>
            @if ($data['upcomingAppointments']->isNotEmpty())
                @foreach ($data['upcomingAppointments'] as $appointment)
                    <div class="section-item">
                        <div>
                            <div class="label">
                                {{ $appointment->items->where('itemable_type', 'App\Models\Issue')->first()?->itemable?->description ?? $appointment->activity_type }}
                            </div>
                            <div class="meta">{{ $appointment->vehicle->internal_code }} @
                                {{ $appointment->provider->name }}</div>
                        </div>
                        <span class="badge badge-blue">{{ $appointment->appointment_date->format('d/m') }}</span>
                    </div>
                @endforeach
            @else
                <div class="empty-state">
                    <span>Nessun appuntamento in programma</span>
                </div>
            @endif
        </div>

        <!-- ATTREZZATURE IN SCADENZA -->
        <div class="section">
            <div class="section-header gray">🔧 Attrezzature in scadenza</div>
            @if (!empty($data['expiringEquipment']) && $data['expiringEquipment']->isNotEmpty())
                @foreach ($data['expiringEquipment'] as $equipment)
                    <div class="section-item">
                        <div>
                            <div class="label">{{ $equipment->name }}</div>
                            <div class="meta">{{ $equipment->vehicle->internal_code }} —
                                {{ $equipment->expiration_date->format('d/m/Y') }}</div>
                        </div>
                        <span class="badge {{ $equipment->expiration_date->isPast() ? 'badge-red' : 'badge-yellow' }}">
                            {{ $equipment->expiration_date->isPast() ? 'Scaduta' : 'In scadenza' }}
                        </span>
                    </div>
                @endforeach
            @else
                <div class="empty-state">
                    <span>Tutte le attrezzature in regola ✅</span>
                </div>
            @endif
        </div>

        <!-- INTERVENTI NECESSARI -->
        <div class="section">
            <div class="section-header gray">⚠️ Interventi necessari</div>
            @if ($data['incompleteVehicles']->isNotEmpty())
                @foreach ($data['incompleteVehicles'] as $vehicle)
                    @php($missingEquipment = $vehicle->missingRequiredEquipment())
                    <div class="section-item">
                        <div>
                            <div class="label">{{ $vehicle->internal_code }}</div>
                            <div class="meta">Manca: {{ $missingEquipment->pluck('name')->join(', ') }}</div>
                        </div>
                        <span class="badge badge-yellow">Da integrare</span>
                    </div>
                @endforeach
            @else
                <div class="empty-state">
                    <span>Nessun intervento necessario ✅</span>
                </div>
            @endif
        </div>

    </div>

    <!-- FOOTER -->
    <div class="footer">
        <p>Questa mail è generata automaticamente da CV Backoffice.</p>
    </div>
</div>
