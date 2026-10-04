@props([
    'storedTires',
    'defaultCheckedTireIds' => [],
])

<div id="tire-section" style="display:none;">
    <div class="field">
        <label>{{ __('Pneumatici in magazzino da montare') }}</label>
        <div class="check-list" id="tire-checkboxes">
            @error('target_tire_ids')
                <div class="field-error">{{ $message }}</div>
            @enderror
            @foreach ($storedTires as $tire)
                <div class="item tire-checkbox" data-vehicle-id="{{ $tire->vehicle_id }}" style="display:none;">
                    <input type="checkbox" name="target_tire_ids[]" value="{{ $tire->id }}"
                        id="tire_{{ $tire->id }}"
                        {{ in_array((string) $tire->id, old('target_tire_ids', $defaultCheckedTireIds)) ? 'checked' : '' }}>
                    <label class="lbl" for="tire_{{ $tire->id }}">
                        {{ $tire->season_label }} ({{ $tire->position_label }})
                        @if ($tire->brand)
                            <span class="meta">— {{ $tire->brand }}</span>
                        @endif
                    </label>
                </div>
            @endforeach
            <div class="empty" id="no-tire-msg" style="display:none;">
                {{ __('Nessun pneumatico in magazzino per il veicolo selezionato.') }}
            </div>
        </div>
        <div class="hint">{{ __('Seleziona una o più gomme da montare insieme (1, 2 o 4): devono avere la stessa stagionalità e posizioni tutte diverse. Puoi anche descrivere gomme nuove qui sotto, da sole o in aggiunta a quelle selezionate.') }}</div>
    </div>
    <div id="new-tire-fields">
        <div class="row2">
            <div class="field">
                <label for="new_tire_season">{{ __('Stagionalità') }}</label>
                <select class="select @error('new_tire_season') is-invalid @enderror" id="new_tire_season"
                    name="new_tire_season">
                    <option value="" disabled selected>{{ __('Seleziona...') }}</option>
                    <option value="summer" {{ old('new_tire_season') == 'summer' ? 'selected' : '' }}>{{ __('Estive') }}</option>
                    <option value="winter" {{ old('new_tire_season') == 'winter' ? 'selected' : '' }}>{{ __('Invernali') }}</option>
                    <option value="all_season" {{ old('new_tire_season') == 'all_season' ? 'selected' : '' }}>{{ __('Quattro stagioni') }}</option>
                </select>
                @error('new_tire_season')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="field">
                <label for="new_tire_group">{{ __('Quante gomme') }}</label>
                <select class="select" id="new_tire_group" name="new_tire_group">
                    <option value="full_set" {{ old('new_tire_group', 'full_set') == 'full_set' ? 'selected' : '' }}>{{ __('Set completo (4)') }}</option>
                    <option value="front_pair" {{ old('new_tire_group') == 'front_pair' ? 'selected' : '' }}>{{ __('Anteriori (2)') }}</option>
                    <option value="rear_pair" {{ old('new_tire_group') == 'rear_pair' ? 'selected' : '' }}>{{ __('Posteriori (2)') }}</option>
                    <option value="single" {{ old('new_tire_group') == 'single' ? 'selected' : '' }}>{{ __('Singola (1)') }}</option>
                </select>
            </div>
        </div>
        <div class="row2" id="new-tire-position-field" style="display:none;">
            <div class="field">
                <label for="new_tire_position">{{ __('Posizione') }}</label>
                <select class="select @error('new_tire_position') is-invalid @enderror"
                    id="new_tire_position" name="new_tire_position">
                    <option value="" disabled selected>{{ __('Seleziona...') }}</option>
                    <option value="front_left" {{ old('new_tire_position') == 'front_left' ? 'selected' : '' }}>{{ __('Anteriore sinistra') }}</option>
                    <option value="front_right" {{ old('new_tire_position') == 'front_right' ? 'selected' : '' }}>{{ __('Anteriore destra') }}</option>
                    <option value="rear_left" {{ old('new_tire_position') == 'rear_left' ? 'selected' : '' }}>{{ __('Posteriore sinistra') }}</option>
                    <option value="rear_right" {{ old('new_tire_position') == 'rear_right' ? 'selected' : '' }}>{{ __('Posteriore destra') }}</option>
                </select>
                @error('new_tire_position')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>
        <div class="row2">
            <div class="field">
                <label for="new_tire_brand">{{ __('Marca') }}</label>
                <input type="text" class="input" id="new_tire_brand" name="new_tire_brand"
                    value="{{ old('new_tire_brand') }}" placeholder="{{ __('es. Michelin') }}">
            </div>
            <div class="field">
                <label for="new_tire_model_name">{{ __('Modello') }}</label>
                <input type="text" class="input" id="new_tire_model_name" name="new_tire_model_name"
                    value="{{ old('new_tire_model_name') }}" placeholder="{{ __('es. Alpin 6') }}">
            </div>
        </div>
        <x-form.tire-size-input name="new_tire_size" label="{{ __('Misura') }}" vehicle-select="vehicle_id" />
    </div>
</div>
