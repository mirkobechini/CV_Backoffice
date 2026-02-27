@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <h1>{{ $issue->description }}</h1>
        <p><strong>Veicolo:</strong> {{ $issue->vehicle->internal_code }} - {{ $issue->vehicle->brand }} {{ $issue->vehicle->model }}</p>
        <p><strong>Data del guasto:</strong> {{ $issue->event_date }}</p>
        <p><strong>Stato:</strong> {{ $issue->status }}</p>
        @if ($issue->photo)
            <div class="mb-3">
                <strong>Immagine:</strong><br>
                <img src="{{ asset('storage/' . $issue->photo) }}" alt="Immagine del guasto" class="img-fluid mt-2">
            </div>
        @endif

        <a href="{{ route('admin.issues.edit', $issue->id) }}" class="btn btn-primary">Modifica</a>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $issue->id }}">
            Elimina
        </button>

        <x-admin.delete-modal type="issue" :object="$issue" />
    </div>
@endsection