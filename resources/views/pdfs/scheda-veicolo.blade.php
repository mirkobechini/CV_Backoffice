<style>
    @import url('https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap');

    @page {
        size: A4;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'DejaVu Sans', 'Ubuntu', sans-serif;
        font-size: 13px;
        color: #1f2937;
        line-height: 1.5;
        margin: 7.5mm;
        padding: 0;
    }

    /* Icon style */
    .section-icon {
        display: none;
    }

    /* Intestazione */
    .header {
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .header .title h1 {
        font-size: 24px;
        color: #000;
        margin: 0 0 12px 0;
        font-weight: bold;
        text-align: center;
    }

    .header .subtitle {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 6px;
    }

    .header .badge {
        background: #0d6efd;
        color: white;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }

    .header .timestamp {
        text-align: right;
        font-size: 11px;
        color: #6b7280;
        margin: 0;
    }

    /* Hero */
    .hero {
        text-align: center;
        padding: 15px;
        background: #f0f5ff;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .hero .code {
        font-size: 32px;
        font-weight: bold;
        color: #0d6efd;
        margin: 0;
    }

    .hero .plate {
        font-size: 15px;
        color: #374151;
        margin-top: 4px;
        font-weight: 500;
    }

    /* Sezioni */
    .section-title {
        display: flex;
        align-items: center;
        line-height: 1.2;
        font-size: 13px;
        font-weight: bold;
        color: white;
        background: #0d6efd;
        padding: 8px 12px;
        margin-top: 15px;
        margin-bottom: 10px;
        border-radius: 4px;
    }

    /* Info card */
    .info-card {
        border: 1px solid #d1d5db;
        border-radius: 6px;
        padding: 10px;
        page-break-inside: avoid;
    }

    .info-card h3 {
        font-size: 13px;
        color: #0d6efd;
        margin-bottom: 8px;
        border-bottom: 2px solid #e5e7eb;
        padding-bottom: 4px;
        font-weight: bold;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 4px 0;
        font-size: 12px;
        align-items: center;
    }

    .info-row .label {
        color: #6b7280;
        font-weight: 500;
    }

    .info-row .value {
        font-weight: 600;
        color: #1f2937;
        text-align: right;
    }

    /* Tabelle */
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
        font-size: 12px;
    }

    th {
        background: #f3f4f6;
        border-bottom: 2px solid #d1d5db;
        padding: 6px 8px;
        text-align: left;
        font-weight: 600;
        color: #374151;
        font-size: 12px;
        font-family: 'DejaVu Sans', 'Ubuntu', sans-serif;
    }

    td {
        padding: 5px 8px;
        border-bottom: 1px solid #e5e7eb;
    }

    tr:last-child td {
        border-bottom: none;
    }

    tr:nth-child(even) {
        background: #f9fafb;
    }

    /* Tag stati */
    .tag {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    .tag-red {
        background: #fee2e2;
        color: #991b1b;
    }

    .tag-green {
        background: #dcfce7;
        color: #166534;
    }

    .tag-yellow {
        background: #fef3c7;
        color: #b45309;
    }

    .tag-blue {
        background: #dbeafe;
        color: #1e40af;
    }

    /* Messaggio vuoto */
    .empty {
        color: #9ca3af;
        font-style: italic;
        padding: 10px 0;
        font-size: 12px;
    }

    /* Footer */
    .footer {
        text-align: center;
        font-size: 10px;
        color: #9ca3af;
        border-top: 1px solid #e5e7eb;
        padding-top: 10px;
        margin-top: 15px;
    }

    /* Page break control */
    .section {
        page-break-inside: avoid;
    }

    .info-grid {
        display: flex;
        gap: 22px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    .info-grid .info-card {
        flex: 1;
        min-width: 48%;
        margin-bottom: 8px;
    }

    .page-break {
        page-break-before: always;
    }
</style>

<!-- INTESTAZIONE -->
<div class="header">
    <div class="title">
        <h1>SCHEDA VEICOLO</h1>
    </div>
    <div class="subtitle">
        <span class="badge">{{ $vehicle->vehicleType->name }}</span>
        <p class="timestamp">Stampata il {{ date('d/m/Y H:i') }}</p>
    </div>
</div>

<!-- HERO -->
<div class="hero">
    <div class="code">{{ $vehicle->internal_code }}</div>
    <div class="plate">{{ $vehicle->license_plate }}</div>
</div>

<!-- INFO GRID -->
<div class="info-grid">
    <div class="info-card">
        <h3>Anagrafica</h3>
        <div class="info-row">
            <span class="label">Marca</span>
            <span class="value">{{ $vehicle->brand->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Modello</span>
            <span class="value">{{ $vehicle->carModel->name ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Carburante</span>
            <span class="value">{{ $vehicle->fuel_type }}</span>
        </div>
        <div class="info-row">
            <span class="label">Immatricolazione</span>
            <span class="value">{{ $vehicle->immatricolation_date?->format('d/m/Y') ?? 'N/A' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Chilometri</span>
            <span class="value">{{ number_format($vehicle->mileage ?? 0, 0, ',', '.') }} km</span>
        </div>
    </div>

    <div class="info-card">
        <h3>Documenti & Garanzia</h3>
        <div class="info-row">
            <span class="label">Carta circolazione</span>
            <span class="value">{{ $vehicle->registration_card_path ? 'Disponibile' : 'Non Disponibile' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Garanzia</span>
            <span
                class="value">{{ $vehicle->warranty_expiration_date && $vehicle->is_warranty_expired ? 'Scaduta (' . $vehicle->warranty_expiration_date->format('d/m/Y') . ')' : 'Valida' }}</span>
        </div>
        <div class="info-row">
            <span class="label">Estensione</span>
            <span
                class="value">{{ $vehicle->warranty_extension_duration ? $vehicle->warranty_extension_duration . ' mesi' : 'Nessuna' }}</span>
        </div>
    </div>

</div>

<!-- SCADENZE -->
<div class="section">
    <div class="section-title">Scadenze ({{ $vehicle->deadlines->count() }})</div>
    @if ($vehicle->deadlines->isEmpty())
        <p class="empty">Nessuna scadenza registrata.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Scadenza</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->deadlines as $deadline)
                    <tr>
                        <td>{{ $deadline->type }}</td>
                        <td><strong>{{ $deadline->due_date?->format('d/m/Y') ?? '—' }}</strong></td>
                        <td><span class="tag tag-{{ $deadline->status_color }}">{{ $deadline->status_label }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<!-- GUASTI APERTI -->
<div class="section">
    <div class="section-title">Guasti Aperti ({{ $vehicle->open_issues->count() }})</div>
    @if ($vehicle->open_issues->isEmpty())
        <p class="empty">Nessun guasto aperto.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Descrizione</th>
                    <th>Data</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->open_issues as $issue)
                    <tr>
                        <td>{{ $issue->description }}</td>
                        <td>{{ $issue->event_date?->format('d/m/Y') ?? '—' }}</td>
                        <td><span class="tag tag-{{ $issue->status_color }}">{{ $issue->status_label }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<!-- MANUTENZIONI RECENTI -->
<div class="section">
    <div class="section-title">Ultime Manutenzioni ({{ $vehicle->maintenanceRecords->count() }})</div>
    @if ($vehicle->maintenanceRecords->isEmpty())
        <p class="empty">Nessuna manutenzione registrata.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Officina</th>
                    <th>Intervento</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->maintenanceRecords as $record)
                    <tr>
                        <td>{{ $record->appointment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $record->provider?->name ?? '—' }}</td>
                        <td>{{ $record->items->where('itemable_type', 'App\Models\Issue')->first()?->itemable?->description ?? $record->activity_type }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<!-- STORICO GUASTI -->
<div class="section">
    <div class="section-title">Storico Guasti e Interventi ({{ $vehicle->issues->count() }})</div>
    @if ($vehicle->issues->isEmpty())
        <p class="empty">Nessun guasto registrato.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Guasto</th>
                    <th>Data</th>
                    <th>Intervento</th>
                    <th>Data Intervento</th>
                    <th>Officina</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->issues as $issue)
                    <tr>
                        <td>{{ $issue->description }}</td>
                        <td>{{ $issue->event_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $issue->maintenanceRecords?->first()?->activity_type ?? '—' }}</td>
                        <td>{{ $issue->maintenanceRecords?->first()?->appointment_date?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $issue->maintenanceRecords?->first()?->provider?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<!-- EQUIPAGGIAMENTO -->
<div class="section">
    <div class="section-title">Dotazioni di Bordo ({{ $vehicle->equipment->count() }})</div>
    @if ($vehicle->equipment->isEmpty())
        <p class="empty">Nessuna dotazione registrata.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Matricola</th>
                    <th>Revisione</th>
                    <th>Stato</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($vehicle->equipment as $equipment)
                    <tr>
                        <td>{{ $equipment->name }}</td>
                        <td>{{ $equipment->serial_number }}</td>
                        <td>{{ $equipment->revision_date ? $equipment->revision_date->format('d/m/Y') : '—' }}</td>
                        <td><span class="tag tag-{{ $equipment->status_color }}">{{ $equipment->status_label }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<!-- FOOTER -->
<div class="footer">
    <p>Documento generato automaticamente da CV Backoffice — Dati aggiornati al {{ now()->format('d/m/Y H:i') }}</p>
</div>
