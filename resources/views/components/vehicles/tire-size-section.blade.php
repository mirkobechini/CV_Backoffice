@props(['vehicle' => null])

{{-- Misure pneumatici consigliate + combinazioni ammesse: identico tra
create.blade.php ed edit.blade.php a parte il fallback ai dati esistenti
del veicolo (assente in creazione) — prima duplicato quasi verbatim nei
due file (audit 2026-10-19, C12e). --}}
<div class="field" data-tire-size-group
    data-tire-size-next-index="{{ count(old('allowed_tire_sizes', $vehicle?->allowed_tire_sizes ?? [])) }}">
    <label>{{ __('Misure pneumatici consigliate') }}</label>
    <div class="hint" style="margin-top:0;margin-bottom:8px;">
        {{ __('Un veicolo può averne più di una (es. assale anteriore diverso dal posteriore). Il codice è quello con cui la carta di circolazione identifica la misura (es. "A1"), usato sotto per le combinazioni ammesse.') }}
    </div>
    <div data-tire-size-container>
        @foreach (old('allowed_tire_sizes', $vehicle?->allowed_tire_sizes ?? []) as $i => $entry)
            <x-form.tire-size-input name="allowed_tire_sizes[{{ $i }}][size]" id="allowed_tire_sizes_{{ $i }}"
                :value="$entry['size'] ?? null" label="{{ __('Misura pneumatici') }}" removable
                code-field="allowed_tire_sizes[{{ $i }}][code]" :code-value="$entry['code'] ?? null" />
        @endforeach
    </div>
    <button type="button" class="btn ghost" data-tire-size-add>
        <i class="fa-solid fa-plus"></i> {{ __("Aggiungi un'altra misura") }}
    </button>
    <template data-tire-size-template>
        <x-form.tire-size-input name="allowed_tire_sizes[__INDEX__][size]" id="allowed_tire_sizes_new"
            label="{{ __('Misura pneumatici') }}" removable
            code-field="allowed_tire_sizes[__INDEX__][code]" />
    </template>
</div>
<div class="field" data-combo-group
    data-combo-next-index="{{ count(old('allowed_tire_size_combinations', $vehicle?->allowed_tire_size_combinations ?? [])) }}">
    <label>{{ __('Combinazioni ammesse') }}</label>
    <div class="hint" style="margin-top:0;margin-bottom:8px;">
        {{ __('Quali codici possono stare insieme davanti e dietro (es. A1 davanti con A2 dietro), come indicato sulla carta di circolazione.') }}
    </div>
    <div data-combo-container>
        @foreach (old('allowed_tire_size_combinations', $vehicle?->allowed_tire_size_combinations ?? []) as $i => $combo)
            <div class="row2 combo-row">
                <div class="field">
                    <label>{{ __('Codice anteriore') }}</label>
                    <select class="input combo-code-select" name="allowed_tire_size_combinations[{{ $i }}][front_code]"
                        data-combo-selected="{{ $combo['front_code'] ?? '' }}"
                        data-placeholder="{{ __('Seleziona codice...') }}"></select>
                </div>
                <div class="field">
                    <label>{{ __('Codice posteriore') }}</label>
                    <select class="input combo-code-select" name="allowed_tire_size_combinations[{{ $i }}][rear_code]"
                        data-combo-selected="{{ $combo['rear_code'] ?? '' }}"
                        data-placeholder="{{ __('Seleziona codice...') }}"></select>
                    <button type="button" class="mini-btn combo-remove"
                        aria-label="{{ __('Rimuovi questa combinazione') }}"><i
                            class="fa-solid fa-xmark"></i></button>
                </div>
            </div>
            @error("allowed_tire_size_combinations.{$i}.front_code")
                <div class="field-error">{{ $message }}</div>
            @enderror
            @error("allowed_tire_size_combinations.{$i}.rear_code")
                <div class="field-error">{{ $message }}</div>
            @enderror
        @endforeach
    </div>
    <button type="button" class="btn ghost" data-combo-add>
        <i class="fa-solid fa-plus"></i> {{ __('Aggiungi una combinazione') }}
    </button>
    <template data-combo-template>
        <div class="row2 combo-row">
            <div class="field">
                <label>{{ __('Codice anteriore') }}</label>
                <select class="input combo-code-select" name="allowed_tire_size_combinations[__INDEX__][front_code]"
                    data-placeholder="{{ __('Seleziona codice...') }}"></select>
            </div>
            <div class="field">
                <label>{{ __('Codice posteriore') }}</label>
                <select class="input combo-code-select" name="allowed_tire_size_combinations[__INDEX__][rear_code]"
                    data-placeholder="{{ __('Seleziona codice...') }}"></select>
                <button type="button" class="mini-btn combo-remove"
                    aria-label="{{ __('Rimuovi questa combinazione') }}"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
    </template>
</div>
