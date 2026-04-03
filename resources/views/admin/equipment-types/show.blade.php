@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.equipment-types.index')) }}" class="btn btn-secondary">Torna alla
                    pagina
                    precedente</a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">

                    <div class="card-header">
                        <h1>{{ $equipmentType->name }}</h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Prima revisione:</strong> {{ $equipmentType->first_inspection_months_formatted }}</p>
                        <p><strong>Revisione regolare:</strong> {{ $equipmentType->regular_inspection_months_formatted }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <a href="{{ route('admin.equipment-types.edit', ['equipmentType' => $equipmentType->id, 'back' => url()->full()]) }}"
                class="btn btn-primary">Modifica</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal-{{ $equipmentType->id }}">
                Elimina
            </button>
        </div>

    </div>
    <x-admin.delete-modal type="equipmentType" :object="$equipmentType" />
@endsection
