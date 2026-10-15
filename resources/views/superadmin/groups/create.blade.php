@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Associazioni'), 'url' => route('superadmin.dashboard')],
        ['label' => __('Nuova associazione')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Nuova associazione') }}</h1>
        <a href="{{ route('superadmin.dashboard') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form method="POST" action="{{ route('superadmin.groups.store') }}" data-single-submit="true">
            @csrf

            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Associazione') }}</h2>
                <div class="field" style="margin-bottom:0;">
                    <label for="name">{{ __('Nome associazione') }} <span class="req">*</span></label>
                    <input type="text" class="input @error('name') is-invalid @enderror" id="name" name="name"
                        value="{{ old('name') }}" placeholder="{{ __('es. Croce Verde Esempio') }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">2</span> {{ __('Primo capo') }}</h2>

                <label class="check" style="margin-bottom:12px;">
                    <input type="checkbox" id="self_capo" name="self_capo" value="1" {{ old('self_capo') ? 'checked' : '' }}>
                    <div class="label">{{ __('Sono io il capo di questa associazione') }}</div>
                </label>

                <div id="capo-account-fields">
                    <div class="hint" style="margin:0 0 12px;">
                        {{ __("L'account creato qui diventa capo del nuovo gruppo. Riceverà un'email per impostare la sua password da solo; potrà poi creare sottocapi/membri.") }}
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="capo_name">{{ __('Nome') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('capo_name') is-invalid @enderror" id="capo_name"
                            name="capo_name" value="{{ old('capo_name') }}" placeholder="{{ __('es. Luca Verdi') }}" required>
                        @error('capo_name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field" style="margin-bottom:0;">
                        <label for="capo_email">{{ __('Email') }} <span class="req">*</span></label>
                        <input type="email" class="input @error('capo_email') is-invalid @enderror" id="capo_email"
                            name="capo_email" value="{{ old('capo_email') }}" placeholder="{{ __('es. luca@example.com') }}" required>
                        @error('capo_email')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Creazione...') }}">
                    <i class="fa-solid fa-plus"></i> {{ __('Crea associazione') }}
                </button>
                <a href="{{ route('superadmin.dashboard') }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selfCapo = document.getElementById('self_capo');
                const fields = document.getElementById('capo-account-fields');
                const inputs = fields.querySelectorAll('input');

                const toggle = () => {
                    fields.style.display = selfCapo.checked ? 'none' : '';
                    inputs.forEach(input => input.disabled = selfCapo.checked);
                };

                toggle();
                selfCapo.addEventListener('change', toggle);
            });
        </script>
    @endpush
@endsection
