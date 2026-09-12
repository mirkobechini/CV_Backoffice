@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Utenti')],
    ]" />
@endsection

@php
    $isCapo = auth()->user()->roleIn($group ?? null) === 'capo';
    $roleBadge = fn($role) => match ($role) {
        'capo' => 'b-red',
        'sottocapo' => 'b-amber',
        default => 'b-gray',
    };
    $roleLabel = fn($role) => match ($role) {
        'capo' => __('Capo'),
        'sottocapo' => __('Sottocapo'),
        default => __('Membro'),
    };
@endphp

@section('content')

    <div class="page-header">
        <h1>{{ __('Utenti') }}</h1>
        @if ($isCapo)
            <a href="{{ route('admin.users.create') }}" class="btn primary">
                <i class="fa-solid fa-plus"></i> {{ __('Nuovo utente') }}
            </a>
        @endif
    </div>

    <div class="table-card">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Elenco utenti') }}</h2>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Email') }}</th>
                        <th>{{ __('Ruolo') }}</th>
                        @if ($isCapo)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <span class="name">{{ $user->name }}</span>
                                </div>
                            </td>
                            <td><span class="email">{{ $user->email }}</span></td>
                            <td>
                                @if ($isCapo && $user->pivot->role !== 'capo')
                                    <form method="POST" action="{{ route('admin.users.role', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="role-select" onchange="this.form.submit()">
                                            <option value="sottocapo" @selected($user->pivot->role === 'sottocapo')>
                                                {{ __('Sottocapo') }}</option>
                                            <option value="member" @selected($user->pivot->role === 'member')>
                                                {{ __('Membro') }}</option>
                                        </select>
                                    </form>
                                @else
                                    <span class="badge {{ $roleBadge($user->pivot->role) }}">
                                        {{ $roleLabel($user->pivot->role) }}
                                    </span>
                                @endif
                            </td>
                            @if ($isCapo)
                                <td>
                                    @if ($user->pivot->role !== 'capo')
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                                            onsubmit="return confirm('{{ __('Rimuovere questo utente?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn danger">{{ __('Rimuovi') }}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="empty">{{ __('Non ci sono utenti nel gruppo.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
