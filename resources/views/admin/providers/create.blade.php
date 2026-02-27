@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <h1 class="mb-4">Aggiungi nuova struttura</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="provider-form" method="POST" action="{{ route('admin.providers.store') }}" data-single-submit="true">
                    @csrf
                    <section class="mb-3 row">
                        <h2>Dettagli struttura</h2>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="contact_info" class="form-label">Contatti</label>
                            <input type="text" class="form-control @error('contact_info') is-invalid @enderror"
                                id="contact_info" name="contact_info" value="{{ old('contact_info') }}" required>
                            @error('contact_info')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Indirizzo</label>
                            <input type="text" class="form-control @error('address') is-invalid @enderror" id="address"
                                name="address" value="{{ old('address') }}" required>
                            @error('address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="type" class="form-label">Tipo</label>
                            <input type="text" class="form-control @error('type') is-invalid @enderror" id="type"
                                name="type" value="{{ old('type') }}" required>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <button id="provider-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Aggiungi</button>
                </form>
            </div>
        </div>
    </div>
@endsection
