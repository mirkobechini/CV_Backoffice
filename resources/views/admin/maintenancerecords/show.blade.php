@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.maintenancerecords.index') }}" class="btn btn-secondary">Torna alla lista</a>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card my-4">
                    <div class="card-header">
                        <h1>{{ $maintenanceRecord->issue?->description ?? ($maintenanceRecord->activity_type ?? 'Intervento') }}
                        </h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Mezzo:</strong> {{ $maintenanceRecord->vehicle?->internal_code ?? 'N/A' }}</p>
                        <p><strong>Officina:</strong> {{ $maintenanceRecord->provider?->name ?? 'N/A' }}</p>
                        <p><strong>Appuntamento:</strong> {{ $maintenanceRecord->appointment_date_formatted ?? 'N/A' }}</p>
                        <p><strong>Data completamento:</strong> {{ $maintenanceRecord->return_date_formatted ?? 'N/A' }}</p>
                        @if ($maintenanceRecord->activity_type !== null)
                            <p><strong>Tipo attività:</strong> {{ $maintenanceRecord->activity_type }}</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12">
                @if ($maintenanceRecord?->getKey())
                    <a href="{{ route('admin.maintenancerecords.edit', $maintenanceRecord->getKey()) }}"
                        class="btn btn-primary">Modifica</a>
                @endif
                <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                    data-bs-target="#confirmDeleteModal-{{ $maintenanceRecord->id }}">
                    Elimina
                </button>
            </div>
        </div>
        <x-admin.delete-modal type="maintenancerecord" :object="$maintenanceRecord" />
    </div>
@endsection
