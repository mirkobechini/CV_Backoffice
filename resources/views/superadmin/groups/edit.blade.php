@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Associazioni'), 'url' => route('superadmin.dashboard')],
        ['label' => $group->name],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica associazione') }}</h1>
        <a href="{{ route('superadmin.dashboard') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('superadmin.groups.update', $group) }}" data-single-submit="true">
            @csrf
            @method('PUT')

            <div class="form-section" style="margin-bottom:0;">
                <div class="field" style="margin-bottom:0;">
                    <label for="name">{{ __('Nome associazione') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name', $group->name) }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva') }}
                </button>
                <a href="{{ route('superadmin.dashboard') }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
