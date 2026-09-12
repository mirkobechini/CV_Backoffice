@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Officine'), 'url' => route('admin.providers.index')],
        ['label' => $provider->name],
    ]" />
@endsection

@php
    $typeBadge = match ($provider->type) {
        'Meccanico' => 'b-blue',
        'Elettrauto' => 'b-green',
        'Gommista' => 'b-purple',
        'Lavaggio' => 'b-blue',
        'Allestitore' => 'b-purple',
        'Vetri' => 'b-green',
        'Carrozziere' => 'b-gray',
        'Centro Revisioni' => 'b-amber',
        default => 'b-gray',
    };
@endphp

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.providers.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.providers.edit', ['provider' => $provider->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $provider->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--blue));"><i
                class="fa-solid fa-wrench"></i></div>
        <div class="dl-title">
            <h1>{{ $provider->name }}</h1>
            <div class="sub">{{ $provider->address ?: __('Indirizzo non specificato') }}</div>
        </div>
        <div class="dl-status">
            @if ($provider->type)
                <span class="badge {{ $typeBadge }}">{{ $provider->type }}</span>
            @endif
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli struttura') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Contatti') }}</span>
                <span class="v">{{ $provider->contact_info ?: 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Indirizzo') }}</span>
                <span class="v">{{ $provider->address ?: 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Tipo') }}</span>
                <span class="v">
                    @if ($provider->type)
                        <span class="badge {{ $typeBadge }}">{{ $provider->type }}</span>
                    @else
                        N/A
                    @endif
                </span>
            </div>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Manutenzioni associate') }}</h3>
        </div>
        <div class="body">
            @forelse ($provider->maintenanceRecords as $record)
                <div class="dl-kv">
                    <span class="k">{{ $record->vehicle?->internal_code ?? 'N/A' }} ·
                        {{ $record->items->where('itemable_type', 'App\Models\Issue')->map(fn($item) => $item->itemable?->description)->filter()->implode(', ') ?: $record->activity_type ?? 'N/A' }}
                        <span class="cell-sub">{{ $record->appointment_date_formatted ?? 'N/A' }}</span>
                    </span>
                    <span class="v">
                        <a class="mini-btn"
                            href="{{ route('admin.maintenance-records.show', ['maintenanceRecord' => $record->id, 'back' => url()->full()]) }}"
                            title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                    </span>
                </div>
            @empty
                <p class="empty">{{ __('Nessuna manutenzione associata a questa officina.') }}</p>
            @endforelse
        </div>
    </div>

    <x-admin.delete-modal type="provider" :object="$provider" />
@endsection
