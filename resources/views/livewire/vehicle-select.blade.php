<div class="row2">
    <div class="field">
        <label for="brand_id">{{ __('Marca') }} <span class="req">*</span></label>
        <select id="brand_id" wire:model.live="brand_id" class="select @error('brand_id') is-invalid @enderror"
            name="brand_id" required>
            <option value="">{{ __('Seleziona una marca') }}</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
            @endforeach
        </select>
        @error('brand_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
    <div class="field">
        <label for="car_model_id">{{ __('Modello') }} <span class="req">*</span></label>
        <select id="car_model_id" wire:model="car_model_id" class="select @error('car_model_id') is-invalid @enderror"
            name="car_model_id" required>
            <option value="">{{ __('Seleziona un modello') }}</option>
            @foreach ($models as $model)
                <option value="{{ $model->id }}">{{ $model->name }}</option>
            @endforeach
        </select>
        @error('car_model_id')
            <div class="field-error">{{ $message }}</div>
        @enderror
    </div>
</div>
