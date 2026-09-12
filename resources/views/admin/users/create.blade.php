@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Gruppi'), 'url' => route('admin.groups.index')],
        ['label' => $group->name, 'url' => route('admin.groups.show', $group)],
        ['label' => __('Nuovo utente')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Crea nuovo utente') }}</h1>
        <a href="{{ route('admin.groups.show', $group) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.groups.users.store', $group) }}" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli utente') }}</h2>
                <div class="hint" style="margin:0 0 12px;">
                    {{ __('Il nuovo account verrà aggiunto al gruppo :name.', ['name' => $group->name]) }}
                </div>
                <div class="field">
                    <label for="name">{{ __('Nome') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name') }}" placeholder="{{ __('es. Luca Verdi') }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field">
                    <label for="email">{{ __('Email') }} <span class="req">*</span></label>
                    <input type="email" class="input @error('email') is-invalid @enderror" id="email" name="email"
                        value="{{ old('email') }}" placeholder="{{ __('es. luca@example.com') }}" required>
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="password">{{ __('Password') }} <span class="req">*</span></label>
                        <input type="password" class="input @error('password') is-invalid @enderror" id="password"
                            name="password" placeholder="••••••••" required>
                        @error('password')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="password_confirmation">{{ __('Conferma password') }} <span class="req">*</span></label>
                        <input type="password" class="input" id="password_confirmation" name="password_confirmation"
                            placeholder="••••••••" required>
                    </div>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="role">{{ __('Ruolo') }} <span class="req">*</span></label>
                    <select class="select @error('role') is-invalid @enderror" id="role" name="role" required>
                        <option value="member" @selected(old('role') === 'member')>{{ __('Membro') }}</option>
                        <option value="sottocapo" @selected(old('role') === 'sottocapo')>{{ __('Sottocapo') }}</option>
                        <option value="capo" @selected(old('role') === 'capo')>{{ __('Capo') }}</option>
                    </select>
                    @error('role')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Crea utente') }}
                </button>
                <a href="{{ route('admin.groups.show', $group) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
