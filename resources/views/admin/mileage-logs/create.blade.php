@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Chilometraggi'), 'url' => route('admin.mileage-logs.index')],
        ['label' => __('Nuovo chilometraggio')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo registro chilometri') }}</h1>
        <a href="{{ request('back', route('admin.mileage-logs.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="mileage-log-form" method="POST" action="{{ route('admin.mileage-logs.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli registro chilometri') }}</h2>
                <div class="field">
                    <label for="vehicle_id">{{ __('Veicolo') }} <span class="req">*</span></label>
                    <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id" name="vehicle_id"
                        required>
                        <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                {{ old('vehicle_id') == $vehicle->id ? 'selected' : '' }}>
                                {{ $vehicle->internal_code }} · {{ $vehicle->brand?->name ?? 'N/A' }}
                                {{ $vehicle->carModel?->name ?? 'N/A' }}
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2">
                    <x-form.date-input name="log_date" label="{{ __('Data del registro') }}" required />
                    <div class="field">
                        <label for="mileage">{{ __('Chilometraggio') }} <span class="req">*</span></label>
                        <input type="number" class="input @error('mileage') is-invalid @enderror" id="mileage"
                            name="mileage" value="{{ old('mileage') }}" min="0" placeholder="{{ __('es. 87400') }}"
                            required>
                        @error('mileage')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button id="mileage-log-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva chilometraggio') }}
                </button>
                <a href="{{ request('back', route('admin.mileage-logs.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
