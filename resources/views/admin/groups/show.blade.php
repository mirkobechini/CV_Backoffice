@extends('layouts.app')

@php
    $isCapo = auth()->user()->roleIn($group) === 'capo';
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

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Gruppi'), 'url' => route('admin.groups.index')],
        ['label' => $group->name],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ route('admin.groups.index') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna ai gruppi') }}
        </a>
    </div>

    <div class="g-header">
        <div class="g-avatar"><i class="fa-solid fa-users"></i></div>
        <div class="g-title">
            <h1>{{ $group->name }}</h1>
            <div class="sub">
                {{ __(':users membri · :vehicles veicoli', ['users' => $group->users->count(), 'vehicles' => $group->vehicles->count()]) }}
            </div>
        </div>
        @if ($isCapo)
            <div class="g-actions">
                <button type="button" class="btn sm" onclick="document.getElementById('rename-group').hidden = !document.getElementById('rename-group').hidden">
                    <i class="fa-solid fa-pen"></i> {{ __('Rinomina') }}
                </button>
                <button type="button" class="btn danger sm" data-bs-toggle="modal" data-bs-target="#deleteGroupModal">
                    <i class="fa-solid fa-trash"></i> {{ __('Elimina gruppo') }}
                </button>
            </div>
        @endif
    </div>

    @if ($isCapo)
        <div id="rename-group" hidden style="margin-top:14px;">
            <div class="form-card">
                <form method="POST" action="{{ route('admin.groups.update', $group) }}" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                    @csrf
                    @method('PATCH')
                    <div class="field" style="flex:1; min-width:220px; margin-bottom:0;">
                        <label for="name">{{ __('Nome del gruppo') }}</label>
                        <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                            value="{{ old('name', $group->name) }}" required>
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn primary">{{ __('Rinomina') }}</button>
                </form>
            </div>
        </div>
    @endif

    @error('delete_vehicles')
        <div class="field-error" style="margin-top:14px;">{{ $message }}</div>
    @enderror

    <div class="row2" style="margin-top:16px;">
        <div class="admin-card">
            <div class="head">
                <h3>{{ __('Codice invito') }}</h3>
            </div>
            <div class="body">
                <div class="invite-code">{{ $group->invite_code }}</div>
                <div class="invite-hint">{{ __('Condividi questo codice per far entrare nuovi membri.') }}</div>
                @if ($isCapo)
                    <div class="invite-actions">
                        <form method="POST" action="{{ route('admin.groups.invite-code', $group) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="btn sm">
                                <i class="fa-solid fa-rotate"></i> {{ __('Rigenera codice') }}
                            </button>
                        </form>
                    </div>
                    <div class="invite-email">
                        <h4>{{ __('Invita via email') }}</h4>
                        <form method="POST" action="{{ route('admin.groups.invite', $group) }}" class="email-row">
                            @csrf
                            <input type="email" name="email" class="input" placeholder="email@esempio.it" required>
                            <button type="submit" class="btn primary sm">{{ __('Invia invito') }}</button>
                        </form>
                        @error('email')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
            </div>
        </div>
        <div class="admin-card">
            <div class="head">
                <h3>{{ __('Veicoli del gruppo') }}</h3>
            </div>
            <div class="body">
                <div class="veh-count"><span class="big">{{ $group->vehicles->count() }}</span>
                    {{ __('veicoli assegnati a questo gruppo') }}</div>
            </div>
        </div>
    </div>

    <div class="table-card" style="margin-top:16px;">
        <div class="toolbar">
            <div class="toolbar-left">
                <h2>{{ __('Membri') }}</h2>
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
                    @foreach ($group->users as $member)
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <span class="avatar">{{ mb_strtoupper(mb_substr($member->name, 0, 1)) }}</span>
                                    <span class="name">{{ $member->name }}</span>
                                </div>
                            </td>
                            <td><span class="email">{{ $member->email }}</span></td>
                            <td>
                                @if ($isCapo && $member->pivot->role !== 'capo')
                                    <form method="POST" action="{{ route('admin.groups.role', [$group, $member]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="role" class="role-select" onchange="this.form.submit()">
                                            <option value="sottocapo" @selected($member->pivot->role === 'sottocapo')>
                                                {{ __('Sottocapo') }}</option>
                                            <option value="member" @selected($member->pivot->role === 'member')>
                                                {{ __('Membro') }}</option>
                                        </select>
                                    </form>
                                @else
                                    <span class="badge {{ $roleBadge($member->pivot->role) }}">
                                        {{ $roleLabel($member->pivot->role) }}
                                    </span>
                                @endif
                            </td>
                            @if ($isCapo)
                                <td>
                                    @if ($member->pivot->role !== 'capo')
                                        <form method="POST"
                                            action="{{ route('admin.groups.remove-member', [$group, $member]) }}"
                                            onsubmit="return confirm('{{ __('Rimuovere questo membro?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn danger sm">{{ __('Rimuovi') }}</button>
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

    @if ($isCapo)
        <div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-labelledby="deleteGroupModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="deleteGroupModalLabel">{{ __('Eliminare questo gruppo?') }}
                            </h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="{{ __('Chiudi') }}"></button>
                        </div>
                        <div class="modal-body">
                            {{ __('Questa azione non può essere annullata.') }}
                            @if ($group->vehicles->isNotEmpty())
                                <label class="check" style="margin-top:12px;">
                                    <input type="checkbox" name="delete_vehicles" value="1" required>
                                    <div>
                                        <div class="label">
                                            {{ __('Elimina anche i :n veicoli del gruppo', ['n' => $group->vehicles->count()]) }}
                                        </div>
                                        <div class="sub">{{ __('Necessario per procedere: il gruppo ha veicoli associati.') }}</div>
                                    </div>
                                </label>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annulla') }}</button>
                            <button type="submit" class="btn btn-danger">{{ __('Elimina definitivamente') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
