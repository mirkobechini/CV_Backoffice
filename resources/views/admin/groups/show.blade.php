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
                    <div class="invite-email">
                        <h4>{{ __('Oppure crea un account direttamente') }}</h4>
                        <a href="{{ route('admin.groups.users.create', $group) }}" class="btn sm">
                            <i class="fa-solid fa-user-plus"></i> {{ __('Crea nuovo utente') }}
                        </a>
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

        <div class="admin-card">
            <div class="head">
                <h3>{{ __('Pagina pubblica di stato') }}</h3>
            </div>
            <div class="body">
                <p class="hint" style="margin-bottom:10px;">
                    {{ __('Un link segreto (nessun account richiesto) che mostra solo quali mezzi sono disponibili o in officina, senza dettagli di guasti o dati personali.') }}
                </p>
                @if ($group->public_status_token)
                    @php($publicStatusUrl = route('public.fleet-status', $group->public_status_token))
                    <div class="invite-code" style="font-size:13px; word-break:break-all;">{{ $publicStatusUrl }}</div>
                    @if ($isCapo)
                        <div class="invite-actions">
                            <form method="POST" action="{{ route('admin.groups.public-status-token.generate', $group) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn sm">
                                    <i class="fa-solid fa-rotate"></i> {{ __('Rigenera link') }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.groups.public-status-token.revoke', $group) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn sm danger">
                                    <i class="fa-solid fa-ban"></i> {{ __('Disattiva') }}
                                </button>
                            </form>
                        </div>
                    @endif
                @else
                    <div class="hint">{{ __('Pagina disattivata.') }}</div>
                    @if ($isCapo)
                        <div class="invite-actions">
                            <form method="POST" action="{{ route('admin.groups.public-status-token.generate', $group) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn sm primary">
                                    <i class="fa-solid fa-link"></i> {{ __('Genera link') }}
                                </button>
                            </form>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        <div class="admin-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--green-soft); color:var(--green);"><i
                            class="fa-solid fa-circle-dot"></i></span> {{ __('Cambio gomme stagionale') }}</h3>
            </div>
            <div class="body">
                <p class="hint" style="margin-bottom:10px;">
                    {{ __('Date entro cui la flotta di questo gruppo dovrebbe passare a gomme invernali o estive. Usate dal promemoria e dal riquadro in dashboard.') }}
                </p>
                @if ($isCapo)
                    @php
                        [$winterMonth, $winterDay] = explode('-', $group->winter_switch_date);
                        [$summerMonth, $summerDay] = explode('-', $group->summer_switch_date);
                        $months = [
                            1 => __('Gennaio'), 2 => __('Febbraio'), 3 => __('Marzo'), 4 => __('Aprile'),
                            5 => __('Maggio'), 6 => __('Giugno'), 7 => __('Luglio'), 8 => __('Agosto'),
                            9 => __('Settembre'), 10 => __('Ottobre'), 11 => __('Novembre'), 12 => __('Dicembre'),
                        ];
                    @endphp
                    <form method="POST" action="{{ route('admin.groups.tire-season.update', $group) }}"
                        data-single-submit="true">
                        @csrf
                        @method('PATCH')
                        <div class="row2">
                            <div class="field">
                                <label>{{ __('Passaggio a invernali') }}</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="number" class="input @error('winter_switch_day') is-invalid @enderror"
                                        name="winter_switch_day" min="1" max="31"
                                        value="{{ old('winter_switch_day', (int) $winterDay) }}" style="width:80px;">
                                    <select class="select" name="winter_switch_month">
                                        @foreach ($months as $num => $label)
                                            <option value="{{ $num }}"
                                                {{ (int) old('winter_switch_month', (int) $winterMonth) === $num ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('winter_switch_day')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="field">
                                <label>{{ __('Passaggio a estive') }}</label>
                                <div style="display:flex; gap:8px;">
                                    <input type="number" class="input @error('summer_switch_day') is-invalid @enderror"
                                        name="summer_switch_day" min="1" max="31"
                                        value="{{ old('summer_switch_day', (int) $summerDay) }}" style="width:80px;">
                                    <select class="select" name="summer_switch_month">
                                        @foreach ($months as $num => $label)
                                            <option value="{{ $num }}"
                                                {{ (int) old('summer_switch_month', (int) $summerMonth) === $num ? 'selected' : '' }}>
                                                {{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('summer_switch_day')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <button type="submit" class="btn outline" data-loading-text="{{ __('Salvataggio...') }}">
                            <i class="fa-solid fa-check"></i> {{ __('Salva date') }}
                        </button>
                    </form>
                @else
                    <div class="dl-kv">
                        <span class="k">{{ __('Invernali dal') }}</span>
                        <span class="v">{{ $group->winter_switch_date }}</span>
                    </div>
                    <div class="dl-kv">
                        <span class="k">{{ __('Estive dal') }}</span>
                        <span class="v">{{ $group->summer_switch_date }}</span>
                    </div>
                @endif
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
