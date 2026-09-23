@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Pneumatici'), 'url' => route('admin.tires.index')],
        ['label' => $tire->season_label],
    ]" />
@endsection

@php
    $badgeClass = match ($tire->status) {
        'mounted' => 'b-green',
        'stored' => 'b-gray',
        'retired' => 'b-red',
        default => 'b-gray',
    };
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.tires.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.tires.edit', ['tire' => $tire->id, 'back' => url()->full()]) }}" class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $tire->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--purple));"><i
                class="fa-solid fa-circle-dot"></i></div>
        <div class="dl-title">
            <h1>{{ $tire->season_label }} — {{ trim(($tire->brand ?? '') . ' ' . ($tire->model_name ?? '')) ?: __('N/A') }}
            </h1>
            <div class="sub">{{ $tire->vehicle?->internal_code ?? 'N/A' }} · {{ $tire->size ?: __('N/A') }}</div>
        </div>
        <div class="dl-status">
            <span class="badge {{ $badgeClass }}">{{ $tire->status_label }}</span>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli set di gomme') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Veicolo') }}</span>
                <span class="v">
                    @if ($tire->vehicle)
                        <a class="dl-veh-link" href="{{ route('admin.vehicles.show', $tire->vehicle->id) }}">
                            {{ $tire->vehicle->internal_code }} · {{ $tire->vehicle->license_plate }} ·
                            {{ optional($tire->vehicle->brand)->name }} {{ optional($tire->vehicle->carModel)->name }}
                            <span class="arrow">›</span>
                        </a>
                    @else
                        N/A
                    @endif
                </span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stagionalità') }}</span>
                <span class="v">{{ $tire->season_label }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Posizione') }}</span>
                <span class="v">{{ $tire->position_label }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Marca / Modello') }}</span>
                <span class="v">{{ trim(($tire->brand ?? '') . ' ' . ($tire->model_name ?? '')) ?: 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Misura') }}</span>
                <span class="v">{{ $tire->size ?: 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Stato') }}</span>
                <span class="v"><span class="badge {{ $badgeClass }}">{{ $tire->status_label }}</span></span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Data di montaggio') }}</span>
                <span class="v">{{ $tire->mounted_date_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Km al montaggio') }}</span>
                <span class="v">{{ $tire->mounted_mileage ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Prossimo cambio') }}</span>
                <span class="v">
                    {{ $tire->next_change_date_formatted ?? 'N/A' }}
                    @if ($tire->next_change_mileage)
                        · {{ number_format($tire->next_change_mileage, 0, ',', '.') }} km
                    @endif
                    @if ($tire->change_due)
                        <span class="badge b-amber">{{ __('cambio consigliato') }}</span>
                    @endif
                </span>
            </div>
            @if ($tire->notes)
                <div class="dl-kv">
                    <span class="k">{{ __('Note') }}</span>
                    <span class="v">{{ $tire->notes }}</span>
                </div>
            @endif
        </div>
    </div>

    @if ($tire->status !== 'mounted' && $tire->vehicle)
        <div class="dl-info-card">
            <div class="head">
                <h3>{{ __('Registra montaggio') }}</h3>
            </div>
            <div class="body">
                <form method="POST" action="{{ route('admin.tires.record-change', $tire->id) }}"
                    data-single-submit="true">
                    @csrf
                    <div class="row2">
                        <x-form.date-input name="changed_date" label="{{ __('Data del cambio') }}" required />
                        <div class="field">
                            <label for="mileage_at_change">{{ __('Km al cambio') }}</label>
                            <input type="number" class="input" id="mileage_at_change" name="mileage_at_change"
                                min="0">
                        </div>
                    </div>
                    <div class="field">
                        <label for="previous_disposition">{{ __('Le gomme sostituite') }} <span class="req">*</span></label>
                        <select class="select" id="previous_disposition" name="previous_disposition" required>
                            <option value="stored">{{ __('Vanno in magazzino') }}</option>
                            <option value="retired">{{ __('Vengono dismesse') }}</option>
                        </select>
                        <div class="hint">{{ __('Se il veicolo aveva un set completo montato e qui si monta solo un asse, il set viene diviso: solo la parte effettivamente rimossa segue questa scelta.') }}</div>
                    </div>
                    <div class="field">
                        <label for="notes">{{ __('Note (opzionale)') }}</label>
                        <textarea class="input" id="notes" name="notes" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn primary" data-loading-text="{{ __('Salvataggio...') }}">
                        <i class="fa-solid fa-arrows-rotate"></i> {{ __('Registra cambio gomme') }}
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if ($tire->issues->isNotEmpty())
        <div class="dl-info-card">
            <div class="head">
                <h3>{{ __('Guasti collegati') }}</h3>
            </div>
            <div class="body">
                @foreach ($tire->issues as $issue)
                    <div class="veh-issue-item {{ $issue->status === 'closed' ? 'done' : '' }}">
                        <span class="desc">{{ $issue->description }}</span>
                        <a href="{{ route('admin.issues.show', $issue->id) }}" class="mini-btn"
                            title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($tire->changes->isNotEmpty())
        <div class="table-card" style="margin-top:16px;">
            <div class="toolbar">
                <h2>{{ __('Storico cambi') }}</h2>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Data') }}</th>
                            <th>{{ __('Km') }}</th>
                            <th>{{ __('Set precedente') }}</th>
                            <th>{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tire->changes->sortByDesc('changed_date') as $change)
                            <tr>
                                <td>{{ $change->changed_date_formatted }}</td>
                                <td>{{ $change->mileage_at_change ? number_format($change->mileage_at_change, 0, ',', '.') : '—' }}
                                </td>
                                <td>{{ $change->previousTire?->season_label ?? '—' }}</td>
                                <td>{{ $change->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <x-admin.delete-modal type="tire" :object="$tire" />
@endsection
