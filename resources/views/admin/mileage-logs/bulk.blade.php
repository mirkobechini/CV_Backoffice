@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Chilometraggi'), 'url' => route('admin.mileage-logs.index')],
        ['label' => __('Rilevazione mensile')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Rilevazione mensile chilometri') }}</h1>
        <a href="{{ route('admin.mileage-logs.index') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna ai chilometraggi') }}
        </a>
    </div>

    @if (session('status_error'))
        <div class="notes-card" style="border-color:var(--red);">
            <h4 style="color:var(--red);">{{ session('status_error') }}</h4>
            <p>
            <ul style="margin:0; padding-left:18px;">
                @foreach (session('status_errors', []) as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            </p>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.mileage-logs.bulk-store') }}" data-single-submit="true">
        @csrf

        <div class="table-card">
            <div class="toolbar">
                <div class="toolbar-left">
                    <h2>{{ __('Elenco veicoli') }}</h2>
                </div>
                <div class="filters">
                    <div class="field" style="margin-bottom:0;">
                        <input type="text" class="flatpickr-date" id="log_date" name="log_date"
                            value="{{ old('log_date', date('Y-m-d')) }}" placeholder="gg/mm/aaaa" autocomplete="off"
                            data-alt-class="input @error('log_date') is-invalid @enderror" required>
                        @error('log_date')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Sigla') }}</th>
                            <th>{{ __('Targa') }}</th>
                            <th>{{ __('Marca / Modello') }}</th>
                            <th>{{ __('Ultimo km') }}</th>
                            <th>{{ __('Km attuali') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            <tr>
                                <td class="code">{{ $vehicle->internal_code }}</td>
                                <td class="code">{{ $vehicle->license_plate }}</td>
                                <td>{{ $vehicle->brand?->name ?? 'N/A' }} {{ $vehicle->carModel?->name ?? '' }}</td>
                                <td>{{ $vehicle->mileage !== null ? number_format($vehicle->mileage, 0, ',', '.') : 'N/D' }}
                                </td>
                                <td>
                                    <input type="number" class="input" style="max-width:180px;"
                                        name="mileages[{{ $vehicle->id }}]"
                                        value="{{ old('mileages.' . $vehicle->id) }}" min="0"
                                        placeholder="{{ __('km') }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty">{{ __('Nessun veicolo disponibile.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <span>{{ __('Inserisci solo i km dei mezzi rilevati. I campi vuoti verranno ignorati.') }}</span>
                <button type="submit" class="btn primary" data-loading-text="{{ __('Salvataggio in corso...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva chilometraggi') }}
                </button>
            </div>
        </div>
    </form>
@endsection
