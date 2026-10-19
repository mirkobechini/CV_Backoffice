@props(['appointments'])

<div class="table-card" style="margin-top:16px;">
    <div class="toolbar">
        <h2>{{ __('Storico interventi') }}</h2>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>{{ __('Data') }}</th>
                    <th>{{ __('Tipo') }}</th>
                    <th>{{ __('Officina') }}</th>
                    <th>{{ __('Costo') }}</th>
                    <th>{{ __('Stato') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($appointments as $record)
                    <tr>
                        <td>{{ $record->appointment_date_time_formatted ?? 'N/A' }}</td>
                        <td>{{ $record->activity_type ?? 'N/A' }}</td>
                        <td>{{ $record->provider?->name ?? 'N/A' }}</td>
                        <td>{{ $record->cost !== null ? '€ ' . number_format((float) $record->cost, 2, ',', '.') : '—' }}</td>
                        <td>
                            <span class="badge {{ $record->return_date ? 'b-green' : 'b-blue' }}">
                                {{ $record->return_date ? __('Completato') : __('In programma') }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('admin.maintenance-records.show', $record->id) }}" class="mini-btn"
                                title="{{ __('Visualizza') }}" aria-label="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="empty">{{ __('Nessun intervento registrato.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
