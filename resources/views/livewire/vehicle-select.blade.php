<?php

use Livewire\Component;


?>

<div>
    <div class="mb-3">
        <label for="brand_id" class="form-label">Marca</label>
        <select id="brand_id" wire:model.live="brand_id" class="form-control @error('brand_id') is-invalid @enderror"
            name ="brand_id" required>
            <option value="">Seleziona una marca</option>
            @foreach ($brands as $brand)
                <option value="{{ $brand->id }}">{{ $brand->name }}</option>
            @endforeach
        </select>
        @error('brand_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    <div class="mb-3">
        <label for="car_model_id" class="form-label">Modello</label>
        <select id="car_model_id" wire:model="car_model_id"
            class="form-control @error('car_model_id') is-invalid @enderror" name="car_model_id" required>
            <option value="">Seleziona un modello</option>
            @foreach ($models as $model)
                <option value="{{ $model->id }}">{{ $model->name }}</option>
            @endforeach
        </select>
        @error('car_model_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
