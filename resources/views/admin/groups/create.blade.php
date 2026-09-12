@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Gruppi'), 'url' => route('admin.groups.index')],
        ['label' => __('Nuovo gruppo')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Crea nuovo gruppo') }}</h1>
        <a href="{{ route('admin.groups.index') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('admin.groups.store') }}" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli gruppo') }}</h2>
                <div class="field">
                    <label for="name">{{ __('Nome del gruppo') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name') }}" placeholder="{{ __('es. Flotta Principale') }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Crea gruppo') }}
                </button>
                <a href="{{ route('admin.groups.index') }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
