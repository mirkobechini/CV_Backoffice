@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Torna ai gruppi</a>
            </div>
        </div>
        <h1 class="mb-4">Crea nuovo gruppo</h1>
        <div class="card my-0">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.groups.store') }}" data-single-submit="true">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Nome del gruppo</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                            name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Crea gruppo</button>
                </form>
            </div>
        </div>
    </div>
@endsection
