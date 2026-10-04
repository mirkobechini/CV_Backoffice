@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Appuntamenti'), 'url' => route('admin.maintenance-records.index')],
        ['label' => __('Nuovo appuntamento')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo appuntamento') }}</h1>
        <a href="{{ request('back', route('admin.maintenance-records.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="maintenance-record-form" method="POST" action="{{ route('admin.maintenance-records.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf

            {{-- Sezione 1: Dettagli veicolo --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Dettagli veicolo') }}</h2>
                <div class="field">
                    <label for="vehicle_id">{{ __('Veicolo') }} <span class="req">*</span></label>
                    <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id" name="vehicle_id"
                        required>
                        <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                data-mileage="{{ $vehicle->mileage }}"
                                data-mileage-date="{{ $vehicle->latestMileageLog?->log_date_formatted }}"
                                data-tire-sizes="{{ json_encode($vehicle->allowed_tire_sizes ?? []) }}"
                                {{ (string) old('vehicle_id', $preselectedVehicleId ?? '') === (string) $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->internal_code }}</option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <x-admin.maintenance-item-picker
                    :open-issues="$openIssues"
                    :closed-issues="$closedIssues"
                    :pending-deadlines="$pendingDeadlines"
                    :default-checked-issue-ids="$preselectedIssueId ? [(string) $preselectedIssueId] : []"
                />
            </div>

            {{-- Sezione 2: Dettagli officina --}}
            <div class="form-section">
                <h2><span class="num">2</span> {{ __('Dettagli officina') }}</h2>
                <div class="field">
                    <label for="provider_id">{{ __('Officina') }} <span class="req">*</span></label>
                    <select class="select @error('provider_id') is-invalid @enderror" id="provider_id"
                        name="provider_id" required>
                        <option value="" disabled selected>{{ __("Seleziona un'officina") }}</option>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->id }}"
                                {{ old('provider_id') == $provider->id ? 'selected' : '' }}>
                                {{ $provider->name }}</option>
                        @endforeach
                    </select>
                    @error('provider_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            {{-- Sezione 3: Dettagli appuntamento --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">3</span> {{ __('Dettagli appuntamento') }}</h2>
                <div class="row2">
                    <x-form.date-input name="appointment_date" label="{{ __('Data appuntamento') }}" required />
                    <div class="field">
                        <label for="activity_type">{{ __('Tipo attività') }}</label>
                        <select class="select @error('activity_type') is-invalid @enderror" id="activity_type"
                            name="activity_type">
                            <option value="" disabled {{ old('activity_type', $preselectedActivityType) ? '' : 'selected' }}>{{ __('Seleziona...') }}</option>
                            @foreach (\App\Models\MaintenanceRecord::ACTIVITY_TYPES as $item)
                                <option value="{{ $item }}" {{ old('activity_type', $preselectedActivityType) == $item ? 'selected' : '' }}>
                                    {{ $item }}
                                </option>
                            @endforeach
                        </select>
                        @error('activity_type')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <x-admin.maintenance-tire-section :stored-tires="$storedTires" />

                <div class="row2">
                    <div class="field">
                        <x-form.date-input name="return_date" label="{{ __('Data restituzione veicolo') }}" />
                        <div class="hint">{{ __("Compila per segnare l'appuntamento come completato.") }}</div>
                    </div>
                    <div class="field">
                        <label for="mileage_at_service">{{ __("Chilometraggio all'appuntamento") }}</label>
                        <input type="number" class="input @error('mileage_at_service') is-invalid @enderror"
                            id="mileage_at_service" name="mileage_at_service" value="{{ old('mileage_at_service') }}"
                            min="0" placeholder="es. 87400">
                        @error('mileage_at_service')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        <div class="hint" id="mileage-last-known-hint"></div>
                        <div class="hint">
                            {{ __("Obbligatorio se è selezionato un tagliando — km al momento del conferimento in officina.") }}
                        </div>
                    </div>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="notes">{{ __('Note') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes"
                        rows="3" placeholder="{{ __('Annotazioni facoltative su questo appuntamento...') }}">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="maintenance-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Aggiungi appuntamento') }}
                </button>
                <a href="{{ request('back', route('admin.maintenance-records.index')) }}"
                    class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    <x-admin.maintenance-completion-modal />

    @include('admin.maintenance-records._form-script')
@endsection
