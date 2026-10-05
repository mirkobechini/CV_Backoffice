@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Attrezzature')],
        ['label' => __('Appuntamenti Attrezzature'), 'url' => route('admin.equipment-maintenance-records.index')],
        ['label' => __('Nuovo appuntamento')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo appuntamento') }}</h1>
        <a href="{{ request('back', route('admin.equipment-maintenance-records.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <form id="equipment-maintenance-record-form" method="POST"
            action="{{ route('admin.equipment-maintenance-records.store') }}" data-single-submit="true">
            @csrf

            {{-- Sezione 1: Attrezzature coinvolte --}}
            <div class="form-section">
                <h2><span class="num">1</span> {{ __('Attrezzature coinvolte') }}</h2>
                <div class="field">
                    <label>{{ __('Attrezzature') }} <span class="req">*</span></label>
                    <div class="check-list" id="equipment-checkboxes">
                        @error('equipment_ids')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        @php($selectedEquipmentIds = old('equipment_ids', $selectedEquipmentId ? [(string) $selectedEquipmentId] : []))
                        @foreach ($equipments as $equipment)
                            <div class="item">
                                <input type="checkbox" class="equipment-checkbox" name="equipment_ids[]"
                                    value="{{ $equipment->id }}" id="equipment_{{ $equipment->id }}"
                                    {{ in_array((string) $equipment->id, $selectedEquipmentIds) ? 'checked' : '' }}>
                                <label class="lbl" for="equipment_{{ $equipment->id }}">
                                    {{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}
                                    @if ($equipment->vehicle)
                                        <span class="meta">— {{ $equipment->vehicle->internal_code }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="field" id="issue-section" style="display:none;">
                    <label>{{ __('Guasti collegati') }}</label>
                    <div class="check-list" id="issue-checkboxes">
                        @error('issue_ids')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                        @foreach ($openIssues as $issue)
                            <div class="item issue-checkbox" data-equipment-id="{{ $issue->equipment_id }}"
                                style="display:none;">
                                <input type="checkbox" name="issue_ids[]" value="{{ $issue->id }}"
                                    id="issue_{{ $issue->id }}"
                                    {{ in_array((string) $issue->id, old('issue_ids', [])) ? 'checked' : '' }}>
                                <label class="lbl" for="issue_{{ $issue->id }}">
                                    {{ $issue->description }}
                                    @if ($issue->event_date)
                                        <span class="meta">— {{ $issue->event_date->format('d/m/Y') }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                        <div class="empty" id="no-issue-msg" style="display:none;">
                            {{ __('Nessun guasto aperto per le attrezzature selezionate.') }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sezione 2: Dettagli appuntamento --}}
            <div class="form-section" style="margin-bottom:0;">
                <h2><span class="num">2</span> {{ __('Dettagli appuntamento') }}</h2>
                <div class="row2">
                    <div class="field">
                        <label for="provider_id">{{ __('Fornitore') }} <span class="req">*</span></label>
                        <select class="select @error('provider_id') is-invalid @enderror" id="provider_id"
                            name="provider_id" required>
                            <option value="" disabled selected>{{ __('Seleziona un fornitore') }}</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}"
                                    {{ old('provider_id') == $provider->id ? 'selected' : '' }}>
                                    {{ $provider->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('provider_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="field">
                        <label for="activity_type">{{ __('Tipologia attività') }}</label>
                        <select class="select @error('activity_type') is-invalid @enderror" id="activity_type"
                            name="activity_type">
                            <option value="" disabled selected>{{ __('Seleziona...') }}</option>
                            @foreach (\App\Models\EquipmentMaintenanceRecord::ACTIVITY_TYPES as $item)
                                <option value="{{ $item }}" {{ old('activity_type') == $item ? 'selected' : '' }}>
                                    {{ $item }}</option>
                            @endforeach
                        </select>
                        @error('activity_type')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="row2">
                    <x-form.date-input name="appointment_date" label="{{ __('Data appuntamento') }}" required />
                    <x-form.date-input name="return_date" label="{{ __('Data restituzione') }}" />
                </div>
                <div class="field">
                    <label for="cost">{{ __('Costo (€)') }}</label>
                    <input type="number" step="0.01" class="input @error('cost') is-invalid @enderror"
                        id="cost" name="cost" value="{{ old('cost') }}" min="0" placeholder="es. 120.00">
                    @error('cost')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="notes">{{ __('Note (opzionale)') }}</label>
                    <textarea class="input @error('notes') is-invalid @enderror" id="notes" name="notes"
                        rows="3">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-actions">
                <button id="equipment-maintenance-record-submit-btn" type="submit" class="btn primary lg"
                    data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva') }}
                </button>
                <a href="{{ request('back', route('admin.equipment-maintenance-records.index')) }}"
                    class="btn">{{ __('Annulla') }}</a>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const equipmentCheckboxes = Array.from(document.querySelectorAll('.equipment-checkbox'));
                const issueSection = document.getElementById('issue-section');
                const issueItems = Array.from(document.querySelectorAll('.issue-checkbox'));
                const noIssueMsg = document.getElementById('no-issue-msg');

                const checkedEquipmentIds = () => equipmentCheckboxes.filter(c => c.checked).map(c => c.value);

                const filterIssues = () => {
                    const selected = checkedEquipmentIds();
                    issueSection.style.display = selected.length ? '' : 'none';
                    if (!selected.length) {
                        return;
                    }

                    let hasVisible = false;
                    issueItems.forEach(el => {
                        const matches = selected.includes(el.dataset.equipmentId);
                        el.style.display = matches ? '' : 'none';
                        if (matches) {
                            hasVisible = true;
                        } else {
                            el.querySelector('input').checked = false;
                        }
                    });
                    noIssueMsg.style.display = hasVisible ? 'none' : '';
                };

                equipmentCheckboxes.forEach(cb => cb.addEventListener('change', filterIssues));
                filterIssues();
            });
        </script>
    @endpush
@endsection
