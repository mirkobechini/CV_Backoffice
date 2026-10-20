<style>
    @page {
        size: A4 portrait;
        margin: 15mm;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 11px;
        color: #1f2937;
        line-height: 1.4;
    }

    .header {
        border-bottom: 3px solid #0d6efd;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }

    .header h1 {
        font-size: 18px;
        color: #000;
        margin: 0 0 3px 0;
    }

    .header .date {
        font-size: 10px;
        color: #6b7280;
    }

    .summary {
        margin-bottom: 12px;
        font-size: 10px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
    }

    th {
        background: #f3f4f6;
        border-bottom: 2px solid #d1d5db;
        padding: 5px 6px;
        text-align: left;
        font-weight: 600;
    }

    td {
        padding: 4px 6px;
        border-bottom: 1px solid #e5e7eb;
        vertical-align: top;
    }

    .status-ok {
        color: #15803d;
        font-weight: 600;
    }

    .status-ko {
        color: #b91c1c;
        font-weight: 600;
    }

    .sub {
        display: block;
        color: #6b7280;
        font-size: 8.5px;
    }
</style>

<div class="header">
    <h1>Report conformità flotta</h1>
    <div class="date">Generato il {{ now()->format('d/m/Y') }}</div>
</div>

<div class="summary">
    {{ $rows->count() }} veicoli totali ·
    {{ $rows->where('available', true)->count() }} disponibili ·
    {{ $rows->where('compliant', true)->count() }} in regola con le scadenze
</div>

<table>
    <thead>
        <tr>
            <th>Veicolo</th>
            <th>Tipo</th>
            <th>Stato operativo</th>
            <th>Stato scadenze</th>
            <th>Ultimo km</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row['vehicle']->internal_code }}<span class="sub">{{ $row['vehicle']->license_plate }}</span>
                </td>
                <td>{{ $row['vehicle']->vehicleType?->name ?? '—' }}</td>
                <td class="{{ $row['available'] ? 'status-ok' : 'status-ko' }}">
                    {{ $row['available'] ? 'Disponibile' : 'In officina' }}
                </td>
                <td class="{{ $row['compliant'] ? 'status-ok' : 'status-ko' }}">
                    @if ($row['compliant'])
                        In regola
                    @else
                        Non in regola
                        <span class="sub">{{ implode(', ', $row['non_compliant_types']) }}</span>
                    @endif
                </td>
                <td>
                    @if ($row['vehicle']->latestMileageLog)
                        {{ number_format($row['vehicle']->latestMileageLog->mileage, 0, ',', '.') }} km
                    @else
                        —
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">Nessun veicolo trovato.</td>
            </tr>
        @endforelse
    </tbody>
</table>
