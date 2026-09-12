@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Attrezzature'), 'url' => route('admin.equipments.index')],
        ['label' => __('Tipi Attrezzature'), 'url' => route('admin.equipment-types.index')],
        ['label' => $equipmentType->name],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ request('back', route('admin.equipment-types.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna') }}
        </a>
        <a href="{{ route('admin.equipment-types.edit', ['equipmentType' => $equipmentType->id, 'back' => url()->full()]) }}"
            class="btn primary">
            <i class="fa-solid fa-pen"></i> {{ __('Modifica') }}
        </a>
        <button type="button" class="btn danger" data-bs-toggle="modal"
            data-bs-target="#confirmDeleteModal-{{ $equipmentType->id }}">
            <i class="fa-solid fa-trash"></i> {{ __('Elimina') }}
        </button>
    </div>

    <div class="dl-header">
        <div class="dl-avatar" style="background:linear-gradient(135deg,var(--primary),var(--purple));"><i
                class="fa-solid fa-toolbox"></i></div>
        <div class="dl-title">
            <h1>{{ $equipmentType->name }}</h1>
            <div class="sub">
                {{ __('Prima revisione dopo :n', ['n' => $equipmentType->first_inspection_months_formatted ?? 'N/A']) }}
            </div>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Dettagli tipo di attrezzatura') }}</h3>
        </div>
        <div class="body">
            <div class="dl-kv">
                <span class="k">{{ __('Prima revisione dopo') }}</span>
                <span class="v">{{ $equipmentType->first_inspection_months_formatted ?? 'N/A' }}</span>
            </div>
            <div class="dl-kv">
                <span class="k">{{ __('Revisioni successive ogni') }}</span>
                <span class="v">{{ $equipmentType->regular_inspection_months_formatted ?? 'N/A' }}</span>
            </div>
        </div>
    </div>

    <div class="dl-info-card">
        <div class="head">
            <h3>{{ __('Attrezzature di questo tipo') }}</h3>
        </div>
        <div class="body">
            @forelse ($equipmentType->equipments as $equipment)
                <div class="dl-kv">
                    <span class="k">
                        <a class="dl-veh-link" href="{{ route('admin.equipments.show', $equipment->id) }}">
                            {{ $equipment->name }}
                            <span class="arrow">›</span>
                        </a>
                    </span>
                    <span class="v">{{ $equipment->serial_number ?? 'N/A' }}</span>
                </div>
            @empty
                <p class="empty">{{ __('Nessuna attrezzatura di questo tipo.') }}</p>
            @endforelse
        </div>
    </div>

    <x-admin.delete-modal type="equipmentType" :object="$equipmentType" />
@endsection
