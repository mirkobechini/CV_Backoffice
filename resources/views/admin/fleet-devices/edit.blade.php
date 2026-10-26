@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Dispositivi pedaggio'), 'url' => route('admin.fleet-devices.index')],
        ['label' => $fleetDevice->code, 'url' => route('admin.fleet-devices.show', $fleetDevice->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica dispositivo') }}</h1>
        <a href="{{ request('back', route('admin.fleet-devices.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="fleet-device-form" method="POST" action="{{ route('admin.fleet-devices.update', $fleetDevice->id) }}"
            data-single-submit="true">
            @csrf
            @method('PUT')

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli dispositivo') }}</h2>
                <p class="hint">{{ __('Per cambiare il veicolo assegnato usa "Riassegna" dalla scheda del dispositivo: qui si modificano solo i dati anagrafici.') }}</p>
                <div class="row2">
                    <div class="field">
                        <label for="type">{{ __('Tipo') }} <span class="req">*</span></label>
                        <select class="select @error('type') is-invalid @enderror" id="type" name="type" required>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}"
                                    {{ old('type', $fleetDevice->type) == $value ? 'selected' : '' }}>{{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="code">{{ __('Codice dispositivo') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('code') is-invalid @enderror" id="code" name="code"
                            value="{{ old('code', $fleetDevice->code) }}" maxlength="255" required>
                        @error('code')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="provider_notes">{{ __('Intestatario/contratto') }}</label>
                        <input type="text" class="input @error('provider_notes') is-invalid @enderror"
                            id="provider_notes" name="provider_notes"
                            value="{{ old('provider_notes', $fleetDevice->provider_notes) }}" maxlength="255">
                        @error('provider_notes')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="notes">{{ __('Note') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes', $fleetDevice->notes) }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="fleet-device-edit-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva modifiche') }}
                </button>
                <a href="{{ request('back', route('admin.fleet-devices.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
