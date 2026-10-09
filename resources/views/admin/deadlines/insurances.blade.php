@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Scadenze'), 'url' => route('admin.deadlines.index')],
        ['label' => __('Assicurazioni')],
    ]" />
@endsection

@section('content')
    @php
        $badgeClass = fn($color) => match ($color) {
            'red' => 'b-red',
            'yellow' => 'b-amber',
            'green' => 'b-green',
            default => 'b-gray',
        };
    @endphp

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Assicurazioni') }}</h2>
                <div class="filters">
                    <span class="chip on">{{ __('Premio totale annuo') }}: € {{ number_format((float) $totalPremium, 2, ',', '.') }}</span>
                </div>
            </div>
            <div class="filters">
                <a href="{{ route('admin.deadlines.index', ['type_filter' => 'Assicurazione']) }}" class="btn"
                    title="{{ __('Vedi nell\'elenco generico scadenze') }}">
                    <i class="fa-solid fa-list"></i> {{ __('Elenco scadenze') }}
                </a>
                <a href="{{ route('admin.csv.export', 'deadlines') }}" class="btn" title="{{ __('Scarica CSV') }}">
                    <i class="fa-solid fa-download"></i> CSV
                </a>
                <a href="{{ route('admin.deadlines.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuova scadenza') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Veicolo') }}</span>
                                <a href="{{ $sortToggleUrl('vehicle') }}" class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Compagnia') }}</span>
                                <a href="{{ $sortToggleUrl('company') }}" class="mini {{ $sortBy === 'company' ? 'on' : '' }}"
                                    title="{{ __('Ordina per compagnia') }}">{{ $sortIcon('company') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Numero polizza') }}</th>
                        <th>{{ __('Coperture') }}</th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Premio annuo') }}</span>
                                <a href="{{ $sortToggleUrl('premium') }}" class="mini {{ $sortBy === 'premium' ? 'on' : '' }}"
                                    title="{{ __('Ordina per premio') }}">{{ $sortIcon('premium') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Scadenza') }}</span>
                                <a href="{{ $sortToggleUrl('date') }}" class="mini {{ $sortBy === 'date' ? 'on' : '' }}"
                                    title="{{ __('Ordina per data') }}">{{ $sortIcon('date') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Stato') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @if ($deadlines->isEmpty())
                        <tr>
                            <td colspan="8" class="empty">{{ __('Nessuna polizza trovata.') }}</td>
                        </tr>
                    @else
                        @foreach ($deadlines as $deadline)
                            <tr>
                                <td class="code">{{ $deadline->vehicle->internal_code ?? 'N/A' }}</td>
                                <td>{{ $deadline->insurance_company ?? 'N/A' }}</td>
                                <td>{{ $deadline->insurance_policy_number ?? 'N/A' }}</td>
                                <td>
                                    @forelse ($deadline->insuranceCoverages as $coverage)
                                        {{ $coverage->coverage_type }}{{ ! $loop->last ? ', ' : '' }}
                                    @empty
                                        N/A
                                    @endforelse
                                </td>
                                <td>€ {{ number_format($deadline->insurance_premium_total, 2, ',', '.') }}</td>
                                <td>{{ $deadline->due_date_formatted ?? 'N/A' }}</td>
                                <td><span class="badge {{ $badgeClass($deadline->status_color) }}">{{ $deadline->status_label }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="mini-btn"
                                            title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                                        <a href="{{ route('admin.deadlines.edit', $deadline->id) }}" class="mini-btn"
                                            title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
@endsection
