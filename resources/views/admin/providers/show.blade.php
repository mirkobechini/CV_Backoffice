@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <h1>{{ $provider->name }}</h1>
        <p><strong>Contatti:</strong> {{ $provider->contact_info }}</p>
        <p><strong>Indirizzo:</strong> {{ $provider->address }}</p>
        <p><strong>Tipo:</strong> {{ $provider->type }}</p>

        <a href="{{ route('admin.providers.edit', $provider->id) }}" class="btn btn-primary">Modifica</a>
        <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal-{{ $provider->id }}">
            Elimina
        </button>

        <x-admin.delete-modal type="provider" :object="$provider" />
    </div>
@endsection