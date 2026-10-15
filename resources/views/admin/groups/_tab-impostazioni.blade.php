@if ($isCapo)
    <div class="admin-card" style="margin-bottom:16px;">
        <div class="head">
            <h3>{{ __('Nome del gruppo') }}</h3>
        </div>
        <div class="body">
            <form method="POST" action="{{ route('admin.groups.update', $group) }}"
                style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                @csrf
                @method('PATCH')
                <div class="field" style="flex:1; min-width:220px; margin-bottom:0;">
                    <label for="name">{{ __('Nome') }}</label>
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

<div class="row2">
    @if ($canManageInvites)
        <div class="admin-card">
            <div class="head">
                <h3>{{ __('Codice invito') }}</h3>
            </div>
            <div class="body">
                <div class="invite-code">{{ $group->invite_code }}</div>
                <div class="invite-hint">{{ __('Condividi questo codice per far entrare nuovi membri.') }}</div>
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
            </div>
        </div>
    @endif

    @if ($canSeeTireSeason)
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
                        <button type="submit" class="btn outline-solid" data-loading-text="{{ __('Salvataggio...') }}">
                            <i class="fa-solid fa-check"></i> {{ __('Salva date') }}
                        </button>
                    </form>
                @else
                    @php
                        [$winterMonth, $winterDay] = explode('-', $group->winter_switch_date);
                        [$summerMonth, $summerDay] = explode('-', $group->summer_switch_date);
                    @endphp
                    <div class="dl-kv">
                        <span class="k">{{ __('Invernali dal') }}</span>
                        <span class="v">{{ (int) $winterDay }}/{{ (int) $winterMonth }}</span>
                    </div>
                    <div class="dl-kv">
                        <span class="k">{{ __('Estive dal') }}</span>
                        <span class="v">{{ (int) $summerDay }}/{{ (int) $summerMonth }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

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

    @if ($isCapo)
        <div class="admin-card">
            <div class="head">
                <h3>{{ __('Permessi sottocapi') }}</h3>
            </div>
            <div class="body">
                <p class="hint" style="margin-bottom:10px;">
                    {{ __('Per impostazione predefinita solo il capo gestisce codice invito, inviti via email e creazione account. Puoi estendere questa gestione ai sottocapi.') }}
                </p>
                <form method="POST" action="{{ route('admin.groups.invite-permission.update', $group) }}"
                    data-single-submit="true">
                    @csrf
                    @method('PATCH')
                    <label class="check">
                        <input type="checkbox" name="sottocapo_can_manage_invites" value="1"
                            @checked($group->sottocapo_can_manage_invites) onchange="this.form.submit()">
                        <div>
                            <div class="label">{{ __('I sottocapi possono gestire gli inviti') }}</div>
                        </div>
                    </label>
                </form>
            </div>
        </div>
    @endif
</div>
