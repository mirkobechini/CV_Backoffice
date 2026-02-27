@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.providers.index') }}" class="btn btn-secondary">Torna alla lista</a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card my-4">
                        <div class="card-header">
                            <h1>{{ $provider->name }}</h1>
                        </div>
                        <div class="card-body">
                            <p><strong>Contatti:</strong> {{ $provider->contact_info }}</p>
                            <p><strong>Indirizzo:</strong> {{ $provider->address }}</p>
                            <p><strong>Tipo:</strong> {{ $provider->type }}</p>
                        </div>
                </div>
            </div>
            <div class="col-12">

                <a href="{{ route('admin.providers.edit', $provider->id) }}" class="btn btn-primary">Modifica</a>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                    data-bs-target="#confirmDeleteModal-{{ $provider->id }}">
                    Elimina
                </button>

            </div>
        </div>
    </div>
    <x-admin.delete-modal type="provider" :object="$provider" />
@endsection
