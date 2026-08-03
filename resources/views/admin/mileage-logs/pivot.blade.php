@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12 d-flex gap-2">
                <a href="{{ route('admin.mileage-logs.index') }}" class="btn btn-secondary">Torna ai chilometraggi</a>
            </div>
        </div>

        <h1 class="mb-4">Chilometraggi {{ $year }}</h1>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="mb-3">
            <form method="GET" action="{{ route('admin.mileage-logs.pivot') }}" class="row g-2 align-items-center">
                <div class="col-auto">
                    <label for="year" class="col-form-label">Anno:</label>
                </div>
                <div class="col-auto">
                    <select name="year" id="year" class="form-select" onchange="this.form.submit()">
                        @foreach (range(date('Y'), date('Y') - 5) as $y)
                            <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.mileage-logs.pivot-save') }}">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">

            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Sigla</th>
                            <th>Targa</th>
                            <th>Mezzo</th>
                            @foreach (['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'] as $m)
                                <th class="text-center" style="min-width:90px;">{{ $m }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($vehicles as $vehicle)
                            @php $vehicleLogs = $logs->get($vehicle->id) ?? collect(); @endphp
                            <tr>
                                <td><strong>{{ $vehicle->internal_code }}</strong></td>
                                <td>{{ $vehicle->license_plate }}</td>
                                <td>{{ $vehicle->brand?->name ?? '' }} {{ $vehicle->carModel?->name ?? '' }}</td>
                                @foreach (range(1, 12) as $month)
                                    @php
                                        $log = $vehicleLogs->get($month);
                                        $km = $log->mileage ?? null;
                                        $inputName = "mileages[{$vehicle->id}][{$month}]";
                                    @endphp
                                    <td class="text-center p-1">
                                        <input type="number"
                                            class="form-control form-control-sm text-center @if ($km) border-success @endif"
                                            name="{{ $inputName }}" value="{{ old($inputName, $km) }}" min="0"
                                            placeholder="—" style="max-width: 100px;">
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center">Nessun veicolo disponibile.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="text-muted">I valori già salvati hanno bordo verde. Modifica e salva.</span>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Salva modifiche
                </button>
            </div>
        </form>
    </div>
@endsection
