@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature'), 'url' => route('admin.equipments.index')],
        ['label' => $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A')],
    ]" />
@endsection

@php
    $badgeClass = match ($equipment->status_color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
    $collaudoBadgeClass = match ($equipment->collaudo_status_color) {
        'red' => 'b-red',
        'yellow' => 'b-amber',
        'green' => 'b-green',
        default => 'b-gray',
    };
    $category = $equipment->equipmentType->category ?? null;
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.equipments.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.equipments.edit', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $equipment->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--purple));"><i
                class="fa-solid fa-fire-extinguisher"></i></div>
        <div class="dl-title">
            <h1>{{ $equipment->name ?: ($equipment->equipmentType->name ?? 'N/A') }}</h1>
            <div class="sub">{{ $equipment->equipmentType->name ?? 'N/A' }} ·
                {{ $equipment->serial_number ?: __('N/A') }}</div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $badgeClass }}">{{ $equipment->status_label }}</span>
            @if ($equipment->needs_exchange)
                <span class="badge b-red">{{ __('Da sostituire') }}</span>
            @endif
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli attrezzatura') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Tipo di attrezzatura') }}</span>
                <span class="v">{{ $equipment->equipmentType->name ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Marca / Modello') }}</span>
                <span class="v">{{ trim(($equipment->brand ?? '') . ' ' . ($equipment->model ?? '')) ?: 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Numero di serie / matricola') }}</span>
                <span class="v">{{ $equipment->serial_number ?: 'N/A' }}</span>
            </div>
            @if ($equipment->identification_number)
                <div class="dl-kv">
                    <span class="k">{{ __('Numero identificativo') }}</span>
                    <span class="v">{{ $equipment->identification_number }}</span>
                </div>
            @endif
            @if ($equipment->fabrication_date)
                <div class="dl-kv">
                    <span class="k">{{ __('Data di fabbricazione') }}</span>
                    <span class="v">{{ $equipment->fabrication_date_formatted }}</span>
                </div>
            @endif
            <div class="dl-kv">
                <span class="k">{{ __('Data revisione') }}</span>
                <span class="v">{{ $equipment->revision_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data scadenza') }}</span>
                <span class="v">{{ $equipment->expiration_date_formatted ?? 'N/A' }}</span>
            </div>

            @if ($category === 'fire_extinguisher')
                @if ($equipment->extinguisher_agent)
                    <div class="dl-kv">
                        <span class="k">{{ __('Agente estinguente') }}</span>
                        <span class="v">{{ $equipment->extinguisher_agent_label }}</span>
                    </div>
                @endif
                @if ($equipment->weight_kg)
                    <div class="dl-kv">
                        <span class="k">{{ __('Peso') }}</span>
                        <span class="v">{{ $equipment->weight_kg }} kg</span>
                    </div>
                @endif
                <div class="dl-kv">
                    <span class="k">{{ __('Data collaudo') }}</span>
                    <span class="v">{{ $equipment->collaudo_date_formatted ?? 'N/A' }}</span>
                </div>
                <div class="dl-kv">
                    <span class="k">{{ __('Prossimo collaudo') }}</span>
                    <span class="v">
                        {{ $equipment->next_collaudo_date_formatted ?? 'N/A' }}
                        <span class="badge {{ $collaudoBadgeClass }}">{{ $equipment->collaudo_status_label }}</span>
                    </span>
                </div>
                <div class="dl-kv">
                    <span class="k">{{ __('Revisioni effettuate') }}</span>
                    <span class="v">
                        {{ $equipment->revision_count }}
                        @if ($equipment->equipmentType->max_revisions_before_exchange)
                            / {{ $equipment->equipmentType->max_revisions_before_exchange }}
                        @endif
                        @if ($equipment->needs_exchange)
                            <span class="badge b-red">{{ __('sostituire') }}</span>
                        @endif
                    </span>
                </div>
            @endif

            @if (in_array($category, ['chair', 'stretcher']))
                @if ($category === 'chair' && $equipment->chair_type)
                    <div class="dl-kv">
                        <span class="k">{{ __('Tipo di sedia') }}</span>
                        <span class="v">{{ $equipment->chair_type_label }}</span>
                    </div>
                @endif
                @if ($equipment->max_weight_kg)
                    <div class="dl-kv">
                        <span class="k">{{ __('Kg massimo consentito') }}</span>
                        <span class="v">{{ $equipment->max_weight_kg }} kg</span>
                    </div>
                @endif
            @endif

            <div class="dl-kv">
                <span class="k">{{ __('Veicolo associato') }}</span>
                <span class="v">
                    @if ($equipment->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $equipment->vehicle->id) }}">
                            {{ $equipment->vehicle->internal_code }} · {{ $equipment->vehicle->license_plate }} ·
                            {{ optional($equipment->vehicle->brand)->name }}
                            {{ optional($equipment->vehicle->carModel)->name }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v"><span class="badge {{ $badgeClass }}">{{ $equipment->status_label }}</span></span>
            </div>
            @if ($equipment->notes)
                <div class="dl-kv">
                    <span class="k">{{ __('Note') }}</span>
                    <span class="v">{{ $equipment->notes }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Registra revisione') }}{{ $category === 'fire_extinguisher' ? ' / ' . __('collaudo') : '' }}</h3>
        </div>
        <div class="body">
            <form method="POST" action="{{ route('admin.equipments.record-revision', $equipment->id) }}"
                data-single-submit="true">
                @csrf
                <div class="row2">
                    <div class="field">
                        <label for="kind">{{ __('Tipo di controllo') }} <span class="req">*</span></label>
                        <select class="select" id="kind" name="kind" required>
                            <option value="revision">{{ __('Revisione') }}</option>
                            @if ($category === 'fire_extinguisher')
                                <option value="collaudo">{{ __('Collaudo') }}</option>
                            @endif
                        </select>
                    </div>
                    <x-form.date-input name="performed_date" label="{{ __('Data') }}" required />
                </div>
                <div class="field">
                    <label for="notes">{{ __('Note (opzionale)') }}</label>
                    <textarea class="input" id="notes" name="notes" rows="2"></textarea>
                </div>
                <button type="submit" class="btn primary" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-clipboard-check"></i> {{ __('Registra') }}
                </button>
            </form>
        </div>
    </div>

    @if ($equipment->revisions->isNotEmpty())
        <div class="table-card" style="margin-top:16px;">
            <div class="toolbar">
                <h2>{{ __('Storico controlli') }}</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Tipo') }}</th>
                            <th>{{ __('Data') }}</th>
                            <th>{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($equipment->revisions->sortByDesc('performed_date') as $revision)
                            <tr>
                                <td>{{ $revision->kind_label }}</td>
                                <td>{{ $revision->performed_date_formatted }}</td>
                                <td>{{ $revision->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <x-admin.delete-modal type="equipment" :object="$equipment" />
@endsection
