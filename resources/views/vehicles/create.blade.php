@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <h1 class="mb-4">Aggiungi nuovo veicolo</h1>
        <div class="card my-0">
            <div class="card-body">
                <form method="POST" action="{{ route('vehicles.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="internal_code" class="form-label">Sigla</label>
                        <input type="number" class="form-control @error('internal_code') is-invalid @enderror"
                            id="internal_code" name="internal_code" value="{{ old('internal_code') }}" required>
                        @error('internal_code')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="brand" class="form-label">Marca</label>
                        <input type="text" class="form-control @error('brand') is-invalid @enderror" id="brand"
                            name="brand" value="{{ old('brand') }}" required>
                        @error('brand')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="model" class="form-label">Modello</label>
                        <input type="text" class="form-control @error('model') is-invalid @enderror" id="model"
                            name="model" value="{{ old('model') }}" required>
                        @error('model')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="license_plate" class="form-label">Targa</label>
                        <input type="text" class="form-control @error('license_plate') is-invalid @enderror"
                            id="license_plate" name="license_plate" value="{{ old('license_plate') }}" required>
                        @error('license_plate')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="immatricolation_date" class="form-label">Data immatricolazione</label>
                        <input type="date" class="form-control @error('immatricolation_date') is-invalid @enderror"
                            id="immatricolation_date" name="immatricolation_date" value="{{ old('immatricolation_date') }}"
                            required>
                        @error('immatricolation_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="registration_card" class="form-label">Carta di circolazione</label>
                        <input type="file" class="form-control @error('registration_card') is-invalid @enderror"
                            id="registration_card" name="registration_card" accept=".pdf,.jpg,.jpeg,.png">
                        @error('registration_card')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label for="type" class="form-label">Tipologia</label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            @foreach ($vehicleTypes as $type)
                                <option value="{{ $type->id }}" {{ old('type') == $type->id ? 'selected' : '' }}>
                                    {{ $type->name }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Salva</button>
                </form>
            </div>
        </div>
    </div>
@endsection
