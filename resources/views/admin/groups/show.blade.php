@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.groups.index') }}" class="btn btn-secondary">Torna ai gruppi</a>
            </div>
        </div>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">{{ $group->name }}</h1>
            @if (auth()->user()->roleIn($group) === 'capo')
                <form method="POST" action="{{ route('admin.groups.destroy', $group) }}"
                    onsubmit="return confirm('Eliminare questo gruppo?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Elimina gruppo</button>
                </form>
            @endif
        </div>

        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">Codice invito</div>
                    <div class="card-body">
                        <p class="fs-4 text-center fw-bold">{{ $group->invite_code }}</p>
                        <p class="text-muted text-center">Condividi questo codice per far entrare nuovi membri.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">Veicoli del gruppo</div>
                    <div class="card-body">
                        <p class="mb-0">{{ $group->vehicles->count() }} veicoli assegnati a questo gruppo.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Membri</div>
            <div class="card-body">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Email</th>
                            <th>Ruolo</th>
                            @if (auth()->user()->roleIn($group) === 'capo')
                                <th>Azioni</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group->users as $member)
                            <tr>
                                <td>{{ $member->name }}</td>
                                <td>{{ $member->email }}</td>
                                <td>
                                    @if (auth()->user()->roleIn($group) === 'capo' && $member->pivot->role !== 'capo')
                                        <form method="POST" action="{{ route('admin.groups.role', [$group, $member]) }}"
                                            class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" class="form-select form-select-sm d-inline w-auto"
                                                onchange="this.form.submit()">
                                                <option value="sottocapo" @selected($member->pivot->role === 'sottocapo')>Sottocapo</option>
                                                <option value="member" @selected($member->pivot->role === 'member')>Membro</option>
                                            </select>
                                        </form>
                                    @else
                                        <span
                                            class="badge bg-{{ $member->pivot->role === 'capo' ? 'danger' : ($member->pivot->role === 'sottocapo' ? 'warning' : 'secondary') }}">
                                            {{ ucfirst($member->pivot->role) }}
                                        </span>
                                    @endif
                                </td>
                                @if (auth()->user()->roleIn($group) === 'capo')
                                    <td>
                                        @if ($member->pivot->role !== 'capo')
                                            <form method="POST"
                                                action="{{ route('admin.groups.remove-member', [$group, $member]) }}"
                                                onsubmit="return confirm('Rimuovere questo membro?');">
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
    </div>
@endsection
