@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Associazioni'), 'url' => route('superadmin.dashboard')],
        ['label' => $group->name],
        ['label' => $currentLicense ? __('Rinnova licenza') : __('Assegna licenza')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ $currentLicense ? __('Rinnova licenza') : __('Assegna licenza') }} — {{ $group->name }}</h1>
        <a href="{{ route('superadmin.dashboard') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    @if ($currentLicense)
        <div class="hint" style="margin-bottom:16px;">
            {{ __('Licenza attuale') }}: {{ ucfirst($currentLicense->tier) }},
            {{ $currentLicense->max_vehicles ?? '∞' }} {{ __('veicoli') }},
            {{ $currentLicense->max_users ?? '∞' }} {{ __('utenti') }}
            @if ($currentLicense->isExpired())
                — <strong>{{ __('scaduta') }}</strong>
            @endif
            . {{ __('Confermando qui si crea un nuovo periodo di licenza: lo storico resta.') }}
        </div>
    @endif

    <div class="form-card">
        <form method="POST" action="{{ route('superadmin.licenses.store', $group) }}" data-single-submit="true">
            @csrf

            <div class="form-section" style="margin-bottom:0;">
                <div class="row2">
                    <div class="field">
                        <label for="tier">{{ __('Tier') }} <span class="req">*</span></label>
                        <select class="select @error('tier') is-invalid @enderror" id="tier" name="tier" required>
                            @foreach (\App\Models\License::TIERS as $tier)
                                <option value="{{ $tier }}" {{ old('tier') === $tier ? 'selected' : '' }}>
                                    {{ ucfirst($tier) }}
                                </option>
                            @endforeach
                        </select>
                        @error('tier')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        <div class="hint">{{ __('Precompila i campi sotto; restano modificabili per un accordo su misura.') }}</div>
                    </div>
                    <div class="field">
                        <label for="list_price_eur">{{ __('Prezzo di listino (€/mese)') }}</label>
                        <input type="number" step="0.01" min="0" class="input @error('list_price_eur') is-invalid @enderror"
                            id="list_price_eur" name="list_price_eur" value="{{ old('list_price_eur') }}">
                        @error('list_price_eur')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="max_vehicles">{{ __('Limite veicoli') }}</label>
                        <input type="number" min="0" class="input @error('max_vehicles') is-invalid @enderror"
                            id="max_vehicles" name="max_vehicles" value="{{ old('max_vehicles') }}"
                            placeholder="{{ __('vuoto = illimitato') }}">
                        @error('max_vehicles')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="max_users">{{ __('Limite utenti') }}</label>
                        <input type="number" min="0" class="input @error('max_users') is-invalid @enderror"
                            id="max_users" name="max_users" value="{{ old('max_users') }}"
                            placeholder="{{ __('vuoto = illimitato') }}">
                        @error('max_users')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="field">
                    <label>{{ __('Funzionalità premium incluse') }}</label>
                    <label class="check">
                        <input type="checkbox" name="features[]" value="{{ \App\Models\License::FEATURE_STATISTICS }}"
                            {{ in_array(\App\Models\License::FEATURE_STATISTICS, old('features', [])) ? 'checked' : '' }}>
                        <div class="label">{{ __('Statistiche guasti') }}</div>
                    </label>
                    <label class="check">
                        <input type="checkbox" name="features[]" value="{{ \App\Models\License::FEATURE_AI_BACKFILL }}"
                            {{ in_array(\App\Models\License::FEATURE_AI_BACKFILL, old('features', [])) ? 'checked' : '' }}>
                        <div class="label">{{ __('Backfill AI parti guasto') }}</div>
                    </label>
                </div>

                <div class="row2">
                    <div class="field">
                        <label for="starts_at">{{ __('Inizio') }} <span class="req">*</span></label>
                        <input type="date" class="input @error('starts_at') is-invalid @enderror" id="starts_at"
                            name="starts_at" value="{{ old('starts_at', now()->toDateString()) }}" required>
                        @error('starts_at')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="expires_at">{{ __('Scadenza') }}</label>
                        <input type="date" class="input @error('expires_at') is-invalid @enderror" id="expires_at"
                            name="expires_at" value="{{ old('expires_at') }}" placeholder="{{ __('vuoto = senza scadenza') }}">
                        @error('expires_at')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="field">
                    <label for="discount_code">{{ __('Codice sconto (opzionale)') }}</label>
                    <input type="text" class="input @error('discount_code') is-invalid @enderror" id="discount_code"
                        name="discount_code" value="{{ old('discount_code') }}" style="text-transform:uppercase;">
                    @error('discount_code')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label for="notes">{{ __('Note') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva licenza') }}
                </button>
                <a href="{{ route('superadmin.dashboard') }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const presets = @json($tierPresets);
                const tierSelect = document.getElementById('tier');
                const maxVehicles = document.getElementById('max_vehicles');
                const maxUsers = document.getElementById('max_users');
                const listPrice = document.getElementById('list_price_eur');
                const featureCheckboxes = document.querySelectorAll('input[name="features[]"]');

                const applyPreset = () => {
                    const preset = presets[tierSelect.value];
                    if (!preset) return;
                    maxVehicles.value = preset.max_vehicles ?? '';
                    maxUsers.value = preset.max_users ?? '';
                    listPrice.value = preset.list_price_eur ?? '';
                    featureCheckboxes.forEach(cb => {
                        cb.checked = preset.features.includes(cb.value);
                    });
                };

                tierSelect.addEventListener('change', applyPreset);
            });
        </script>
    @endpush
@endsection
