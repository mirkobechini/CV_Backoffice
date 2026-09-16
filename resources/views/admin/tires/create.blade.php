@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Pneumatici'), 'url' => route('admin.tires.index')],
        ['label' => __('Nuovo set di gomme')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo set di gomme') }}</h1>
        <a href="{{ request('back', route('admin.tires.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="tire-form" method="POST" action="{{ route('admin.tires.store') }}" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli set di gomme') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="vehicle_id">{{ __('Veicolo') }} <span class="req">*</span></label>
                        <select class="select @error('vehicle_id') is-invalid @enderror" id="vehicle_id"
                            name="vehicle_id" required>
                            <option value="" disabled selected>{{ __('Seleziona un veicolo') }}</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}"
                                    {{ old('vehicle_id', $selectedVehicleId) == $vehicle->id ? 'selected' : '' }}>
                                    {{ $vehicle->internal_code }} · {{ $vehicle->brand->name ?? 'N/A' }}
                                    {{ $vehicle->carModel->name ?? 'N/A' }}
                                </option>
                            @endforeach
                        </select>
                        @error('vehicle_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="season">{{ __('Stagionalità') }} <span class="req">*</span></label>
                        <select class="select @error('season') is-invalid @enderror" id="season" name="season"
                            required>
                            <option value="" disabled selected>{{ __('Seleziona una stagionalità') }}</option>
                            <option value="summer" {{ old('season') == 'summer' ? 'selected' : '' }}>
                                {{ __('Estive') }}</option>
                            <option value="winter" {{ old('season') == 'winter' ? 'selected' : '' }}>
                                {{ __('Invernali') }}</option>
                            <option value="all_season" {{ old('season') == 'all_season' ? 'selected' : '' }}>
                                {{ __('Quattro stagioni') }}</option>
                        </select>
                        @error('season')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="axle">{{ __('Asse') }} <span class="req">*</span></label>
                        <select class="select @error('axle') is-invalid @enderror" id="axle" name="axle" required>
                            <option value="full" {{ old('axle', 'full') == 'full' ? 'selected' : '' }}>
                                {{ __('Set completo (4)') }}</option>
                            <option value="front" {{ old('axle') == 'front' ? 'selected' : '' }}>
                                {{ __('Anteriori (2)') }}</option>
                            <option value="rear" {{ old('axle') == 'rear' ? 'selected' : '' }}>
                                {{ __('Posteriori (2)') }}</option>
                        </select>
                        @error('axle')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="quantity">{{ __('Numero di gomme') }} <span class="req">*</span></label>
                        <input type="number" class="input @error('quantity') is-invalid @enderror" id="quantity"
                            name="quantity" value="{{ old('quantity', 4) }}" min="1" max="10" required>
                        @error('quantity')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="status">{{ __('Stato') }} <span class="req">*</span></label>
                        <select class="select @error('status') is-invalid @enderror" id="status" name="status"
                            required>
                            <option value="" disabled selected>{{ __('Seleziona uno stato') }}</option>
                            <option value="mounted" {{ old('status') == 'mounted' ? 'selected' : '' }}>
                                {{ __('Montate') }}</option>
                            <option value="stored" {{ old('status', 'stored') == 'stored' ? 'selected' : '' }}>
                                {{ __('In magazzino') }}</option>
                            <option value="retired" {{ old('status') == 'retired' ? 'selected' : '' }}>
                                {{ __('Dismesse') }}</option>
                        </select>
                        @error('status')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="brand">{{ __('Marca') }}</label>
                        <input type="text" class="input @error('brand') is-invalid @enderror" id="brand"
                            name="brand" value="{{ old('brand') }}" placeholder="{{ __('es. Michelin') }}">
                        @error('brand')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="model_name">{{ __('Modello') }}</label>
                        <input type="text" class="input @error('model_name') is-invalid @enderror" id="model_name"
                            name="model_name" value="{{ old('model_name') }}" placeholder="{{ __('es. Alpin 6') }}">
                        @error('model_name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="size">{{ __('Misura') }}</label>
                    <input type="text" class="input @error('size') is-invalid @enderror" id="size" name="size"
                        value="{{ old('size') }}" placeholder="{{ __('es. 205/55 R16') }}">
                    @error('size')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2">
                    <x-form.date-input name="mounted_date" label="{{ __('Data di montaggio') }}" />
                    <div class="field">
                        <label for="mounted_mileage">{{ __('Km al montaggio') }}</label>
                        <input type="number" class="input @error('mounted_mileage') is-invalid @enderror"
                            id="mounted_mileage" name="mounted_mileage" value="{{ old('mounted_mileage') }}" min="0">
                        @error('mounted_mileage')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <x-form.date-input name="next_change_date" label="{{ __('Prossimo cambio (data)') }}" />
                    <div class="field">
                        <label for="next_change_mileage">{{ __('Prossimo cambio (km)') }}</label>
                        <input type="number" class="input @error('next_change_mileage') is-invalid @enderror"
                            id="next_change_mileage" name="next_change_mileage"
                            value="{{ old('next_change_mileage') }}" min="0">
                        @error('next_change_mileage')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="field">
                    <label for="notes">{{ __('Note (opzionale)') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="tire-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva') }}
                </button>
                <a href="{{ request('back', route('admin.tires.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
