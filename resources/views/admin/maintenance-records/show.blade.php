@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.maintenance-records.index')) }}" class="btn btn-secondary">Torna alla
                    pagina precedente</a>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-12">
                <div class="card my-4">
                    <div class="card-header">
                        <h1>{{ $maintenanceRecord->items->where('itemable_type', 'App\\Models\\Issue')->first()?->itemable?->description ?? ($maintenanceRecord->activity_type ?? 'Intervento') }}
                        </h1>
                    </div>
                    <div class="card-body">
                        <p><strong>Mezzo:</strong> {{ $maintenanceRecord->vehicle?->internal_code ?? 'N/A' }}</p>
                        <p><strong>Officina:</strong> {{ $maintenanceRecord->provider?->name ?? 'N/A' }}</p>
                        <p><strong>Appuntamento:</strong> {{ $maintenanceRecord->appointment_date_formatted ?? 'N/A' }}</p>
                        <p><strong>Data completamento:</strong> {{ $maintenanceRecord->return_date_formatted ?? 'N/A' }}</p>
                        @if ($maintenanceRecord->mileage_at_service !== null)
                            <p><strong>Km all'appuntamento:</strong>
                                {{ number_format($maintenanceRecord->mileage_at_service, 0, ',', '.') }}</p>
                        @endif
                        @if ($maintenanceRecord->activity_type !== null)
                            <p><strong>Tipo attività:</strong> {{ $maintenanceRecord->activity_type }}</p>
                        @endif
                        @if ($maintenanceRecord->recurrence_months || $maintenanceRecord->recurrence_km)
                            <hr>
                            <h6>Prossimo tagliando</h6>
                            @if ($maintenanceRecord->next_due_date)
                                <p class="mb-1"><strong>Scadenza:</strong>
                                    {{ $maintenanceRecord->next_due_date->format('d/m/Y') }}
                                    <span class="badge bg-{{ $maintenanceRecord->recurrence_status === 'expired' ? 'danger' : ($maintenanceRecord->recurrence_status === 'expiring' ? 'warning text-dark' : 'success') }} ms-2">
                                        {{ $maintenanceRecord->recurrence_status_label }}
                                    </span>
                                </p>
                            @endif
                            @if ($maintenanceRecord->next_due_km)
                                <p class="mb-0"><strong>Km scadenza:</strong>
                                    {{ number_format($maintenanceRecord->next_due_km, 0, ',', '.') }}</p>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12">
                @if ($maintenanceRecord->items->where('itemable_type', 'App\\Models\\Issue')->first()?->itemable?->status !== 'closed')
                    <x-admin.complete-maintenance-modal :maintenanceRecord="$maintenanceRecord" />
                @endif
                @if ($maintenanceRecord?->getKey())
                    <a href="{{ route('admin.maintenance-records.edit', ['maintenanceRecord' => $maintenanceRecord->getKey(), 'back' => url()->full()]) }}"
                        class="btn btn-primary">Modifica</a>
                @endif
                <button type="button" class="btn btn-danger" data-bs-toggle="modal"
                    data-bs-target="#confirmDeleteModal-{{ $maintenanceRecord->id }}">
                    Elimina
                </button>
            </div>
        </div>
        <x-admin.delete-modal type="maintenanceRecord" :object="$maintenanceRecord" />

    </div>
@endsection
