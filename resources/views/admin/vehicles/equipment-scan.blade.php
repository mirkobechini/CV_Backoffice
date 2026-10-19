@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Veicoli'), 'url' => route('admin.vehicles.index')],
        ['label' => $vehicle->internal_code, 'url' => route('admin.vehicles.show', $vehicle->id)],
        ['label' => __('Scansiona attrezzatura')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Scansiona QR attrezzatura') }}</h1>
        <a href="{{ route('admin.vehicles.show', $vehicle->id) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    <div class="form-card">
        <p>{{ __('Inquadra l\'etichetta QR già stampata sull\'attrezzatura per assegnarla a :vehicle.', ['vehicle' => $vehicle->internal_code]) }}</p>

        <div id="equipment-qr-reader" style="width:100%; max-width:400px; margin:16px auto;"></div>
        <p id="equipment-scan-status" class="hint" style="text-align:center;"></p>

        <form id="equipment-scan-assign-form" method="POST"
            action="{{ route('admin.vehicles.equipment.assign', $vehicle) }}">
            @csrf
            <input type="hidden" id="equipment-scan-id" name="equipment_id" value="">
        </form>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/equipment-qr-scan.js'])
@endpush
