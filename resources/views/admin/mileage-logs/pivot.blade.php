@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Chilometraggi'), 'url' => route('admin.mileage-logs.index')],
        ['label' => __('Vista mensile')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Chilometraggi :year', ['year' => $year]) }}</h1>
        <a href="{{ route('admin.mileage-logs.index') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna ai chilometraggi') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.mileage-logs.pivot-save') }}">
        @csrf
        <input type="hidden" name="year" value="{{ $year }}">

        <div class="table-card">
            <div class="toolbar">
                <div class="toolbar-left">
                    <h2>{{ __('Elenco veicoli') }}</h2>
                </div>
                <div class="filters">
                    <select class="select" style="width:auto;" name="year_select"
                        onchange="location.href = this.value">
                        @foreach (range(date('Y'), date('Y') - 5) as $y)
                            <option
                                value="{{ route('admin.mileage-logs.pivot', ['year' => $y]) }}"
                                {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Sigla') }}</th>
                            <th>{{ __('Targa') }}</th>
                            <th>{{ __('Mezzo') }}</th>
                            @foreach (['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'] as $m)
                                <th style="text-align:center; min-width:90px;">{{ $m }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            @php($vehicleLogs = $logs->get($vehicle->id) ?? collect())
                            <tr>
                                <td class="code">{{ $vehicle->internal_code }}</td>
                                <td class="code">{{ $vehicle->license_plate }}</td>
                                <td>{{ $vehicle->brand?->name ?? '' }} {{ $vehicle->carModel?->name ?? '' }}</td>
                                @foreach (range(1, 12) as $month)
                                    <td style="text-align:center; padding:6px;">
                                        <input type="number" class="input"
                                            style="max-width:90px; text-align:center;{{ ($vehicleLogs->get($month)->mileage ?? null) ? ' border-color:var(--green);' : '' }}"
                                            name="mileages[{{ $vehicle->id }}][{{ $month }}]"
                                            value="{{ old('mileages.' . $vehicle->id . '.' . $month, $vehicleLogs->get($month)->mileage ?? null) }}"
                                            min="0" placeholder="—">
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="empty">{{ __('Nessun veicolo disponibile.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="pagination">
                <span>{{ __('I valori già salvati hanno bordo verde. Modifica e salva.') }}</span>
                <button type="submit" class="btn primary">
                    <i class="fa-solid fa-check"></i> {{ __('Salva modifiche') }}
                </button>
            </div>
        </div>
    </form>
@endsection
