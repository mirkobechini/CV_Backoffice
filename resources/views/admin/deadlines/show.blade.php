@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">

                    <div class="card-header">
                        <h1>{{ $deadline->type }}</h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Veicolo:</strong> {{ $deadline->vehicle->internal_code }} -
                            {{ $deadline->vehicle->brand }}
                            {{ $deadline->vehicle->model }}</p>
                        <p><strong>Data di scadenza:</strong> {{ $deadline->due_date_formatted ?? 'N/A' }}</p>
                        <p><strong>Stato:</strong>
                            @switch($deadline->automatic_status)
                                @case('renewed')
                                    <span class="badge bg-success">Rinnovata</span>
                                @break

                                @case('pending')
                                    <span class="badge bg-warning text-dark">In scadenza</span>
                                @break

                                @case('expired')
                                    <span class="badge bg-danger">Scaduta</span>
                                @break

                                @default
                                    <span class="badge bg-secondary">Sconosciuto</span>
                            @endswitch
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <a href="{{ route('admin.deadlines.edit', ['deadline' => $deadline->id, 'back' => url()->full()]) }}"
                class="btn btn-primary">Modifica</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal-{{ $deadline->id }}">
                Elimina
            </button>
        </div>

    </div>
    <x-admin.delete-modal type="deadline" :object="$deadline" />
@endsection
