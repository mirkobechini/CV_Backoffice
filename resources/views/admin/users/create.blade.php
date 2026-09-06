@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Torna agli utenti</a>
            </div>
        </div>
        <h1 class="mb-4">Crea nuovo utente</h1>
        <div class="card my-0">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.store') }}" data-single-submit="true">
                    @csrf
                    <div class="mb-3">
                        <label for="name" class="form-label">Nome</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                            name="name" value="{{ old('name') }}" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email"
                            name="email" value="{{ old('email') }}" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password"
                            name="password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Conferma password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation"
                            required>
                    </div>
                    <div class="mb-3">
                        <label for="role" class="form-label">Ruolo</label>
                        <select class="form-select @error('role') is-invalid @enderror" id="role" name="role"
                            required>
                            <option value="member" @selected(old('role') === 'member')>Membro</option>
                            <option value="sottocapo" @selected(old('role') === 'sottocapo')>Sottocapo</option>
                            <option value="capo" @selected(old('role') === 'capo')>Capo</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Crea utente</button>
                </form>
            </div>
        </div>
    </div>
@endsection
