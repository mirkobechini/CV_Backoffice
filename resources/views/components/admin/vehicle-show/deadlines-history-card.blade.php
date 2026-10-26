@props(['vehicle'])

@if ($vehicle->deadlines->isNotEmpty())
    <div class="table-card" style="margin-top:16px;">
        <div class="toolbar">
            <h2>{{ __('Storico Scadenze') }}</h2>
        </div>
        <div class="table-responsive" style="padding:12px 16px;">
            @foreach ($vehicle->deadlines->sortByDesc('due_date')->groupBy('type') as $type => $typeDeadlines)
                <h3 style="font-size:13px;font-weight:600;margin:12px 0 4px;">{{ $type }}</h3>
                <table class="veh-hist-table">
                    <thead>
                        <tr>
                            <th>{{ __('Data scadenza') }}</th>
                            <th>{{ __('Stato') }}</th>
                            <th>{{ __('Rinnovata') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($typeDeadlines as $deadline)
                            <tr>
                                <td>{{ $deadline->due_date?->format('d/m/Y') ?? 'N/A' }}</td>
                                <td>
                                    <x-admin.status-badge :color="$deadline->status_color">{{ $deadline->status_label }}</x-admin.status-badge>
                                </td>
                                <td>
                                    @if ($deadline->is_renewed)
                                        <i class="fa-solid fa-check" style="color:var(--green)"></i>
                                    @else
                                        <i class="fa-solid fa-xmark" style="color:var(--text-muted)"></i>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </div>
    </div>
@endif
