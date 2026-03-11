@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.equipment-types.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <h1 class="mb-4">Modifica tipologia di attrezzatura</h1>
        <div class="card my-0">
            <div class="card-body">
                <form id="equipmenttype-form" method="POST" action="{{ route('admin.equipment-types.update', $equipmentType->id) }}"
                    enctype="multipart/form-data" data-single-submit="true">
                    @csrf
                    @method('PUT')
                    <section class="mb-3 row">
                        <h2>Dettagli Tipologia di attrezzatura</h2>
                        <div class="mb-3">
                            <label for="name" class="form-label">Nome</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name"
                                name="name" value="{{ old('name', $equipmentType->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="first_inspection_months" class="form-label">Prima revisione (mesi)</label>
                            <input type="text" class="form-control @error('first_inspection_months') is-invalid @enderror" id="first_inspection_months"
                                name="first_inspection_months" value="{{ old('first_inspection_months', $equipmentType->first_inspection_months) }}" required>
                            @error('first_inspection_months')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mb-3">
                            <label for="regular_inspection_months" class="form-label">Revisione regolare (mesi)</label>
                            <input type="text" class="form-control @error('regular_inspection_months') is-invalid @enderror"
                                id="regular_inspection_months" name="regular_inspection_months" value="{{ old('regular_inspection_months', $equipmentType->regular_inspection_months) }}">
                            @error('regular_inspection_months')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </section>
                    <button id="equipmenttype-submit-btn" type="submit" class="btn btn-primary"
                        data-loading-text="Salvataggio...">Salva modifica</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('equipmenttype-form').addEventListener('submit', function() {
            const submitButton = document.getElementById('equipmenttype-submit-btn');
            submitButton.disabled = true;
            submitButton.innerText = submitButton.getAttribute('data-loading-text');
        });
    </script>
@endsection
