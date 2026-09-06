@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Gruppi</h1>
            <a href="{{ route('admin.groups.create') }}" class="btn btn-primary">Nuovo gruppo</a>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header">Entra in un gruppo esistente</div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.groups.join') }}" class="row g-2">
                    @csrf
                    <div class="col-auto">
                        <input type="text" name="invite_code" class="form-control" placeholder="Codice invito"
                            maxlength="12" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-outline-primary">Entra</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            @forelse ($groups as $group)
                <div class="col-md-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ $group->name }}</h5>
                            <p class="card-text text-muted">
                                {{ $group->users_count }} membri · {{ $group->vehicles_count }} veicoli
                            </p>
                            <a href="{{ route('admin.groups.show', $group) }}" class="btn btn-sm btn-primary">Gestisci</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info">Non appartieni ancora a nessun gruppo.</div>
                </div>
            @endforelse
        </div>
    </div>
@endsection
