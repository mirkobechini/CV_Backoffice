@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.mileagelogs.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">

                    <div class="card-header">
                        <h1>{{ $mileageLog->vehicle->license_plate }} - {{ $mileageLog->log_date_formatted }}</h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Chilometri:</strong> {{ $mileageLog->mileage }} km</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <a href="{{ route('admin.mileagelogs.edit', ['mileageLog' => $mileageLog->id, 'back' => url()->full()]) }}"
                class="btn btn-primary">Modifica</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal-{{ $mileageLog->id }}">
                Elimina
            </button>
        </div>

    </div>
    <x-admin.delete-modal type="mileageLog" :object="$mileageLog" />
@endsection
