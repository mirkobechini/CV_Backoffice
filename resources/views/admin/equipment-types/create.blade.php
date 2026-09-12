@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature'), 'url' => route('admin.equipments.index')],
        ['label' => __('Tipi Attrezzature'), 'url' => route('admin.equipment-types.index')],
        ['label' => __('Nuovo tipo')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuova tipologia di attrezzatura') }}</h1>
        <a href="{{ request('back', route('admin.equipment-types.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="equipmenttype-form" method="POST" action="{{ route('admin.equipment-types.store') }}"
            enctype="multipart/form-data" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli tipologia di attrezzatura') }}</h2>
                <div class="field">
                    <label for="name">{{ __('Nome') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name') }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="first_inspection_months">{{ __('Dopo quanti mesi la prima revisione') }}</label>
                        <input type="number" class="input @error('first_inspection_months') is-invalid @enderror"
                            id="first_inspection_months" name="first_inspection_months"
                            value="{{ old('first_inspection_months') }}" min="0" placeholder="{{ __('es. 12') }}">
                        @error('first_inspection_months')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="regular_inspection_months">{{ __('Dopo quanti mesi le successive revisioni') }}</label>
                        <input type="number" class="input @error('regular_inspection_months') is-invalid @enderror"
                            id="regular_inspection_months" name="regular_inspection_months"
                            value="{{ old('regular_inspection_months') }}" min="0" placeholder="{{ __('es. 12') }}">
                        @error('regular_inspection_months')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button id="equipmenttype-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Salva') }}
                </button>
                <a href="{{ request('back', route('admin.equipment-types.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
