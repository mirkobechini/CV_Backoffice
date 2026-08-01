<style>
    @page {
        margin: 15mm;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 12px;
        color: #1f2937;
        padding: 20px;
    }

    /* Intestazione */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 12px;
        margin-bottom: 20px;
    }

    .header .title h1 {
        font-size: 22px;
        color: #0d6efd;
    }

    .header .title p {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
    }

    .header .badge {
        background: #0d6efd;
        color: white;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
    }

    /* Codice veicolo in evidenza */
    .hero {
        text-align: center;
        padding: 16px;
        background: #f0f5ff;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .hero .code {
        font-size: 32px;
        font-weight: bold;
        color: #0d6efd;
    }

    .hero .plate {
        font-size: 18px;
        color: #374151;
        margin-top: 4px;
    }

    /* Griglia informazioni */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 20px;
    }

    .info-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
    }

    .info-card h3 {
        font-size: 13px;
        color: #0d6efd;
        margin-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 6px;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 3px 0;
        font-size: 12px;
    }

    .info-row .label {
        color: #6b7280;
    }

    .info-row .value {
        font-weight: 500;
    }

    /* Tabella scadenze */
    .section-title {
        font-size: 14px;
        font-weight: bold;
        color: #0d6efd;
        margin-bottom: 8px;
        margin-top: 16px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
    }

    th {
        background: #f3f4f6;
        text-align: left;
        padding: 8px 10px;
        font-size: 11px;
        text-transform: uppercase;
        color: #6b7280;
    }

    td {
        padding: 7px 10px;
        border-bottom: 1px solid #e5e7eb;
        font-size: 12px;
    }

    .status-ok {
        color: #16a34a;
        font-weight: 600;
    }

    .status-warning {
        color: #d97706;
        font-weight: 600;
    }

    .status-expired {
        color: #dc2626;
        font-weight: 600;
    }

    /* Equipaggiamento */
    .equip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 16px;
    }

    .equip-item {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 6px 10px;
        font-size: 11px;
    }

    .equip-item .name {
        font-weight: 600;
    }

    .equip-item .serial {
        color: #6b7280;
        font-size: 10px;
    }

    /* Guasti */
    .issue-item {
        padding: 6px 0;
        border-bottom: 1px solid #f3f4f6;
        font-size: 12px;
    }

    .issue-item:last-child {
        border-bottom: none;
    }

    .issue-item .desc {
        font-weight: 500;
    }

    .issue-item .meta {
        color: #6b7280;
        font-size: 11px;
    }

    /* Footer */
    .footer {
        text-align: center;
        font-size: 10px;
        color: #9ca3af;
        border-top: 1px solid #e5e7eb;
        padding-top: 10px;
        margin-top: 20px;
    }

    .tag {
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
    }

    .tag-red {
        background: #fef2f2;
        color: #dc2626;
    }

    .tag-green {
        background: #dcfce7;
        color: #16a34a;
    }

    .tag-yellow {
        background: #fffbeb;
        color: #d97706;
    }

    .tag-blue {
        background: #eff6ff;
        color: #2563eb;
    }
</style>


<!-- INTESTAZIONE -->
<div class="header">
    <div class="title">
        <h1>🚛 Scheda Veicolo</h1>
        <p>Stampata il {{ date('d/m/Y') }}</p>
    </div>
    <div class="badge">{{ $vehicle->vehicleType->name }}</div>
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
        <div class="info-row"><span class="label">Marca</span><span class="value">{{ $vehicle->brand->name }}</span>
        </div>
        <div class="info-row"><span class="label">Modello</span><span
                class="value">{{ $vehicle->carModel->name }}</span></div>
        <div class="info-row"><span class="label">Carburante</span><span
                class="value">{{ $vehicle->fuel_type }}</span></div>
        <div class="info-row"><span class="label">Immatricolazione</span><span
                class="value">{{ $vehicle->immatricolation_date->format('d/m/Y') }}</span></div>
        <div class="info-row"><span class="label">Chilometri</span><span
                class="value">{{ number_format($vehicle->mileage, 0, ',', '.') }} km</span></div>
    </div>
    <div class="info-card">
        <h3>Documenti & Garanzia</h3>
        <div class="info-row"><span class="label">Carta circolazione</span><span class="value">
                {{ $vehicle->registration_card_path ? '✅ Presente' : '❌ Assente' }}</span>
        </div>
        <div class="info-row"><span class="label">Garanzia</span><span
                class="value status-{{ $vehicle->is_warranty_expired ? 'expired' : 'valid' }}">{{ $vehicle->is_warranty_expired ? 'Scaduta (' . $vehicle->warranty_expiration_date->format('d/m/Y') . ')' : 'Valida' }}</span>
        </div>
        <div class="info-row"><span class="label">Estensione</span><span
                class="value">{{ $vehicle->warranty_extension_duration ? $vehicle->warranty_extension_duration . ' mesi' : 'Nessuna' }}</span>
        </div>
    </div>
</div>

<!-- SCADENZE -->
<div class="section-title">📅 Scadenze</div>
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
                <td>{{ $deadline->due_date->format('d/m/Y') }}</td>
                <td><span class="tag tag-{{ $deadline->status_color }}">{{ $deadline->status_label }}</span></td>
            </tr>
        @endforeach
    </tbody>
</table>

<!-- GUASTI APERTI -->
<div class="section-title">🔧 Guasti aperti</div>
@if ($vehicle->open_issues->isEmpty())
    <p>Nessun guasto aperto.</p>
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
                    <td>{{ $issue->event_date->format('d/m/Y') }}</td>
                    <td><span class="tag tag-{{ $issue->status_color }}">{{ $issue->status_label }}</span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<!-- EQUIPAGGIAMENTO -->
<div class="section-title">🎒 Dotazioni di bordo</div>
@if ($vehicle->equipment->isEmpty())
    <p>Nessuna dotazione registrata.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Matricola</th>
                <th>Revisione</th>
                <th>Scadenza</th>
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

<!-- MANUTENZIONI RECENTI -->
<div class="section-title">🛠️ Ultime manutenzioni</div>
@if ($vehicle->maintenanceRecords->isEmpty())
    <p>Nessuna manutenzione registrata.</p>
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
                    <td>{{ $record->appointment_date->format('d/m/Y') }}</td>
                    <td>{{ $record->provider?->name }}</td>
                    <td>{{ $record->items->where('itemable_type', 'App\Models\Issue')->first()?->itemable?->description ?? $record->activity_type }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif


<!-- STORICO GUASTI -->
<div class="section-title">📜 Storico guasti e interventi</div>
@if ($vehicle->issues->isEmpty())
    <p>Nessun guasto registrato.</p>
@else
    <table>
        <thead>
            <tr>
                <th>Guasto</th>
                <th>Data guasto</th>
                <th>Intervento</th>
                <th>Data intervento</th>
                <th>Officina</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($vehicle->issues as $issue)
                <tr>
                    <td>{{ $issue->description }}</td>
                    <td>{{ $issue->event_date->format('d/m/Y') }}</td>
                    <td>{{ $issue->maintenanceRecords->first()?->activity_type ?? '—' }}</td>
                    <td>{{ $issue->maintenanceRecords->first()?->appointment_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $issue->maintenanceRecords->first()?->provider?->name ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<!-- FOOTER -->
<div class="footer">
    <p>Documento generato automaticamente da CV Backoffice — Dati aggiornati al {{ now()->format('d/m/Y') }}</p>
</div>
