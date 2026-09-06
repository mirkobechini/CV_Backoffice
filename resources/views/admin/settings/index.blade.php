@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <h1 class="mb-4">Impostazioni</h1>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">Associazione / Gruppo</div>
                    <div class="card-body">
                        @if ($group)
                            @if (auth()->user()->roleIn($group) === 'capo')
                                <form method="POST" action="{{ route('admin.settings.group') }}">
                                    @csrf
                                    @method('PATCH')
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nome</label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                                            id="name" name="name" value="{{ old('name', $group->name) }}" required>
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <button type="submit" class="btn btn-primary">Salva</button>
                                </form>
                            @else
                                <p class="mb-0"><strong>{{ $group->name }}</strong></p>
                                <p class="text-muted">Solo il capo può modificare le impostazioni del gruppo.</p>
                            @endif
                        @else
                            <p class="text-muted">Non appartieni a nessun gruppo.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">Backup database</div>
                    <div class="card-body">
                        <p class="text-muted">Crea un backup del database in formato JSON.</p>
                        <form method="POST" action="{{ route('admin.settings.backup') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">Crea backup</button>
                        </form>

                        @if ($backups->isNotEmpty())
                            <h6 class="mt-4">Backup recenti</h6>
                            <ul class="list-group">
                                @foreach ($backups as $backup)
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>{{ $backup['name'] }}</span>
                                        <span class="text-muted small">{{ round($backup['size'] / 1024, 1) }} KB</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
