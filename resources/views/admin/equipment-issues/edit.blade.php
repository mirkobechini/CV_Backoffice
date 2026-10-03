@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Attrezzature')],
        ['label' => __('Guasti Attrezzature'), 'url' => route('admin.equipment-issues.index')],
        ['label' => $equipmentIssue->description, 'url' => route('admin.equipment-issues.show', $equipmentIssue->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica guasto') }}</h1>
        <a href="{{ request('back', route('admin.equipment-issues.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="equipment-issue-edit-form" method="POST"
            action="{{ route('admin.equipment-issues.update', $equipmentIssue->id) }}" data-single-submit="true">
            @csrf
            @method('PUT')

            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">1</span> {{ __('Dettagli guasto') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="equipment_id">{{ __('Attrezzatura') }} <span class="req">*</span></label>
                        <select class="select @error('equipment_id') is-invalid @enderror" id="equipment_id"
                            name="equipment_id" required>
                            <option value="" disabled>{{ __('Seleziona un\'attrezzatura') }}</option>
                            @foreach ($equipments as $equipment)
                                <option value="{{ $equipment->id }}"
                                    {{ old('equipment_id', $equipmentIssue->equipment_id) == $equipment->id ? 'selected' : '' }}>
                                    {{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}
                                    @if ($equipment->vehicle)
                                        · {{ $equipment->vehicle->internal_code }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('equipment_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <x-form.date-input name="event_date" label="{{ __('Data del guasto') }}" :model="$equipmentIssue"
                        required />
                </div>
                <div class="row2">
                    <div class="field">
                        <label for="status">{{ __('Stato') }} <span class="req">*</span></label>
                        <select class="select @error('status') is-invalid @enderror" id="status" name="status" required>
                            <option value="" disabled>{{ __('Seleziona uno stato') }}</option>
                            <option value="open"
                                {{ old('status', $equipmentIssue->status) == 'open' ? 'selected' : '' }}>{{ __('Aperto') }}
                            </option>
                            <option value="in_progress"
                                {{ old('status', $equipmentIssue->status) == 'in_progress' ? 'selected' : '' }}>
                                {{ __('In lavorazione') }}</option>
                            <option value="closed"
                                {{ old('status', $equipmentIssue->status) == 'closed' ? 'selected' : '' }}>
                                {{ __('Risolto') }}</option>
                        </select>
                        @error('status')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="description">{{ __('Descrizione') }} <span class="req">*</span></label>
                        <input type="text" class="input @error('description') is-invalid @enderror" id="description"
                            name="description" value="{{ old('description', $equipmentIssue->description) }}" required>
                        @error('description')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button id="equipment-issue-edit-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva modifiche') }}
                </button>
                <a href="{{ request('back', route('admin.equipment-issues.index')) }}" class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>
@endsection
