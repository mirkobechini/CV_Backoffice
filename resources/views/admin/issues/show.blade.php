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
                        <h1>{{ $issue->description }}</h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Veicolo:</strong> {{ $issue->vehicle->internal_code }} - {{ $issue->vehicle->brand }}
                            {{ $issue->vehicle->model }}</p>
                        <p><strong>Data del guasto:</strong> {{ $issue->event_date_formatted ?? 'N/A' }}</p>
                        <p><strong>Stato:</strong>
                            @switch($issue->status)
                                @case('open')
                                    <span class="badge bg-danger">Aperto</span>
                                @break

                                @case('in_progress')
                                    <span class="badge bg-warning text-dark">In lavorazione</span>
                                @break

                                @case('closed')
                                    <span class="badge bg-success">Risolto</span>
                                @break

                                @default
                                    <span class="badge bg-secondary">Sconosciuto</span>
                            @endswitch
                        </p>
                        @if ($issue->photo)
                            <div class="card mb-3">
                                <div class="card-body">
                                    <strong>Immagine:</strong><br>
                                    <img src="{{ asset('storage/' . $issue->photo) }}" alt="Immagine del guasto"
                                        class="img-fluid mt-2">
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <a href="{{ route('admin.issues.edit', $issue->id) }}" class="btn btn-primary">Modifica</a>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                data-bs-target="#confirmDeleteModal-{{ $issue->id }}">
                Elimina
            </button>
        </div>

    </div>
    <x-admin.delete-modal type="issue" :object="$issue" />
@endsection
