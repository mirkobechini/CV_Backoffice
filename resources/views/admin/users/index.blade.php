@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Utenti</h1>
            @if (auth()->user()->roleIn($group ?? null) === 'capo')
                <a href="{{ route('admin.users.create') }}" class="btn btn-primary">Nuovo utente</a>
            @endif
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($users->isEmpty())
            <div class="alert alert-info">Non ci sono utenti nel gruppo.</div>
        @else
            <div class="card">
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Ruolo</th>
                                @if (auth()->user()->roleIn($group ?? null) === 'capo')
                                    <th>Azioni</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        @if (auth()->user()->roleIn($group ?? null) === 'capo' && $user->pivot->role !== 'capo')
                                            <form method="POST" action="{{ route('admin.users.role', $user) }}"
                                                class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <select name="role" class="form-select form-select-sm d-inline w-auto"
                                                    onchange="this.form.submit()">
                                                    <option value="sottocapo" @selected($user->pivot->role === 'sottocapo')>Sottocapo
                                                    </option>
                                                    <option value="member" @selected($user->pivot->role === 'member')>Membro</option>
                                                </select>
                                            </form>
                                        @else
                                            <span
                                                class="badge bg-{{ $user->pivot->role === 'capo' ? 'danger' : ($user->pivot->role === 'sottocapo' ? 'warning' : 'secondary') }}">
                                                {{ ucfirst($user->pivot->role) }}
                                            </span>
                                        @endif
                                    </td>
                                    @if (auth()->user()->roleIn($group ?? null) === 'capo')
                                        <td>
                                            @if ($user->pivot->role !== 'capo')
                                                <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                                    onsubmit="return confirm('Rimuovere questo utente?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="btn btn-sm btn-outline-danger">Rimuovi</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
