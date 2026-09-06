<style>
    @page {
        size: A4;
        margin: 15mm;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 12px;
        color: #1f2937;
        line-height: 1.5;
    }

    .header {
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .header h1 {
        font-size: 20px;
        color: #000;
        margin: 0 0 4px 0;
        text-align: center;
    }

    .header .date {
        text-align: center;
        font-size: 11px;
        color: #6b7280;
    }

    .stats-row {
        display: flex;
        gap: 10px;
        margin-bottom: 15px;
    }

    .stat-box {
        flex: 1;
        padding: 10px;
        border-radius: 6px;
        text-align: center;
        font-size: 11px;
    }

    .stat-box .number {
        font-size: 20px;
        font-weight: bold;
        display: block;
    }

    .stat-green {
        background: #dcfce7;
        color: #166534;
    }

    .stat-red {
        background: #fee2e2;
        color: #991b1b;
    }

    .stat-yellow {
        background: #fef3c7;
        color: #b45309;
    }

    .stat-blue {
        background: #dbeafe;
        color: #1e40af;
    }

    .section {
        margin-bottom: 15px;
        page-break-inside: avoid;
    }

    .section-title {
        font-size: 13px;
        font-weight: bold;
        color: white;
        background: #0d6efd;
        padding: 6px 10px;
        border-radius: 4px;
        margin-bottom: 8px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11px;
    }

    th {
        background: #f3f4f6;
        border-bottom: 2px solid #d1d5db;
        padding: 5px 8px;
        text-align: left;
        font-weight: 600;
    }

    td {
        padding: 4px 8px;
        border-bottom: 1px solid #e5e7eb;
    }

    .empty {
        color: #9ca3af;
        font-style: italic;
        padding: 8px 0;
        font-size: 11px;
    }

    .footer {
        text-align: center;
        font-size: 10px;
        color: #9ca3af;
        border-top: 1px solid #e5e7eb;
        padding-top: 10px;
        margin-top: 15px;
    }
</style>

<div class="header">
    <h1>📋 Report riassuntivo - CV Backoffice</h1>
    <div class="date">Generato il {{ now()->format('d/m/Y H:i') }}</div>
</div>

<div class="stats-row">
    <div class="stat-box stat-green">
        <span class="number">{{ $data['vehiclesOk'] }}/{{ $data['totalVehicles'] }}</span>
        Veicoli a posto
    </div>
    <div class="stat-box stat-red">
        <span class="number">{{ $data['openIssues']->count() }}</span>
        Guasti aperti
    </div>
    <div class="stat-box stat-yellow">
        <span class="number">{{ $data['expiredDeadlines']->count() + $data['upcomingDeadlines']->count() }}</span>
        Scadenze in arrivo
    </div>
    <div class="stat-box stat-blue">
        <span class="number">{{ $data['vehiclesInMaintenance'] }}</span>
        In officina
    </div>
</div>

<div class="section">
    <div class="section-title">Scadenze</div>
    @if ($data['expiredDeadlines']->isNotEmpty() || $data['upcomingDeadlines']->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Veicolo</th>
                    <th>Scadenza</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['expiredDeadlines'] as $deadline)
                    <tr>
                        <td>{{ $deadline->type }}</td>
                        <td>{{ $deadline->vehicle->internal_code }}</td>
                        <td>{{ $deadline->due_date->format('d/m/Y') }}</td>
                        <td>Scaduta</td>
                    </tr>
                @endforeach
                @foreach ($data['upcomingDeadlines'] as $deadline)
                    <tr>
                        <td>{{ $deadline->type }}</td>
                        <td>{{ $deadline->vehicle->internal_code }}</td>
                        <td>{{ $deadline->due_date->format('d/m/Y') }}</td>
                        <td>Imminente</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Nessuna scadenza da segnalare.</p>
    @endif
</div>

<div class="section">
    <div class="section-title">Guasti aperti</div>
    @if ($data['openIssues']->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Descrizione</th>
                    <th>Veicolo</th>
                    <th>Data</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['openIssues'] as $issue)
                    <tr>
                        <td>{{ $issue->description }}</td>
                        <td>{{ $issue->vehicle->internal_code }}</td>
                        <td>{{ $issue->event_date->format('d/m/Y') }}</td>
                        <td>{{ $issue->status_label }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Nessun guasto aperto.</p>
    @endif
</div>

<div class="section">
    <div class="section-title">Attrezzature in scadenza</div>
    @if (!empty($data['expiringEquipment']) && $data['expiringEquipment']->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Veicolo</th>
                    <th>Scadenza</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['expiringEquipment'] as $equipment)
                    <tr>
                        <td>{{ $equipment->name }}</td>
                        <td>{{ $equipment->vehicle->internal_code }}</td>
                        <td>{{ $equipment->expiration_date->format('d/m/Y') }}</td>
                        <td>{{ $equipment->expiration_date->isPast() ? 'Scaduta' : 'In scadenza' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Tutte le attrezzature in regola.</p>
    @endif
</div>

<div class="section">
    <div class="section-title">Interventi necessari</div>
    @if ($data['incompleteVehicles']->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Veicolo</th>
                    <th>Manca</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['incompleteVehicles'] as $vehicle)
                    <tr>
                        <td>{{ $vehicle->internal_code }}</td>
                        <td>{{ $vehicle->missingRequiredEquipment()->pluck('name')->join(', ') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">Nessun intervento necessario.</p>
    @endif
</div>

<div class="footer">
    <p>Documento generato automaticamente da CV Backoffice</p>
</div>
