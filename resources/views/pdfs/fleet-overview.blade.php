<style>
    @page {
        size: A4 landscape;
        margin: 12mm;
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

    .sub {
        display: block;
        color: #6b7280;
        font-size: 8.5px;
    }

    .empty-cell {
        color: #9ca3af;
    }
</style>

<div class="header">
    <h1>Riepilogo flotta</h1>
    <div class="date">Generato il {{ now()->format('d/m/Y') }} · {{ $vehicles->count() }} veicoli</div>
</div>

<table>
    <thead>
        <tr>
            <th>Veicolo</th>
            <th>Marca / Modello</th>
            <th>Prossimo tagliando</th>
            <th>Prossima revisione ministeriale</th>
            <th>Prossima revisione ossigeno</th>
            <th>Prossima cinghia</th>
            <th>Ultimo km</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($vehicles as $vehicle)
            @php
                $grouped = $vehicle->deadlines_grouped;
                $tagliando = $grouped->get(\App\Models\Deadline::TYPE_TAGLIANDO);
                $ministerial = $grouped->get(\App\Models\Deadline::TYPE_MINISTERIAL);
                $oxygen = $grouped->get(\App\Models\Deadline::TYPE_OXYGEN);
                $cinghia = $grouped->get(\App\Models\Deadline::TYPE_CINGHIA);

                $deadlineCell = function ($deadline) {
                    if (! $deadline) {
                        return '<span class="empty-cell">&mdash;</span>';
                    }

                    $parts = array_filter([$deadline->date_remaining_label, $deadline->km_remaining_label]);
                    $sub = $parts ? implode(' · ', $parts) : null;

                    $out = e($deadline->due_date_formatted ?? '—');
                    if ($sub) {
                        $out .= '<span class="sub">' . e($sub) . '</span>';
                    }

                    return $out;
                };
            @endphp
            <tr>
                <td>{{ $vehicle->internal_code }}<span class="sub">{{ $vehicle->license_plate }}</span></td>
                <td>{{ $vehicle->brand->name ?? '—' }} {{ $vehicle->carModel->name ?? '' }}</td>
                <td>{!! $deadlineCell($tagliando) !!}</td>
                <td>{!! $deadlineCell($ministerial) !!}</td>
                <td>{!! $deadlineCell($oxygen) !!}</td>
                <td>{!! $deadlineCell($cinghia) !!}</td>
                <td>
                    @if ($vehicle->latestMileageLog)
                        {{ number_format($vehicle->latestMileageLog->mileage, 0, ',', '.') }} km
                        <span class="sub">{{ $vehicle->latestMileageLog->log_date_formatted }}</span>
                    @else
                        <span class="empty-cell">&mdash;</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="empty-cell">Nessun veicolo trovato.</td>
            </tr>
        @endforelse
    </tbody>
</table>
