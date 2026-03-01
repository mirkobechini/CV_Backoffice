@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.issues.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">

                    <div class="card-header">
                        <h1>{{ $vehicleType->name }}</h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Prima revisione dopo:</strong> {{ $vehicleType->first_inspection_months }} @if ($vehicleType->first_inspection_months == 1) mese @else mesi @endif</p>
                        <p><strong>Revisioni successive ogni:</strong> {{ $vehicleType->regular_inspection_months }} @if ($vehicleType->regular_inspection_months == 1) mese @else mesi @endif</p>
                        <p><strong>Numero di estintori richiesti:</strong> {{ $vehicleType->extinguishers_required }}</p>
                        <p><strong>Revisione ossigeno:</strong> {{ $vehicleType->needs_oxygen_check ? 'Sì' : 'No' }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <a href="{{ route('admin.vehicletypes.edit', $vehicleType->id) }}" class="btn btn-primary">Modifica</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal-{{ $vehicleType->id }}">
                Elimina
            </button>
        </div>

    </div>
    <x-admin.delete-modal type="vehicleType" :object="$vehicleType" />
@endsection
