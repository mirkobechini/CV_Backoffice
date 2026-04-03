@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">

                    <div class="card-header">
                        <h1>{{ $equipment->equipmentType->name ?? 'N/A' }}</h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Data revisione:</strong> {{ $equipment->revision_date_formatted  ?? 'N/A' }}</p>
                        <p><strong>Data scadenza:</strong> {{ $equipment->expiration_date_formatted ?? 'N/A' }}</p>
                        <p><strong>Veicolo associato:</strong>
                            @if ($equipment->vehicle)
                                {{ $equipment->vehicle->internal_code }} - {{ $equipment->vehicle->brand->name }} {{ $equipment->vehicle->carModel->name }}
                            @else
                                N/A
                            @endif
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <a href="{{ route('admin.equipments.edit', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
                class="btn btn-primary">Modifica</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal-{{ $equipment->id }}">
                Elimina
            </button>
        </div>

    </div>
    <x-admin.delete-modal type="equipment" :object="$equipment" />
@endsection
