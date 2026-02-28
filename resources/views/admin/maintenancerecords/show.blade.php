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
                @if(!$maintenanceRecord->issue?->status ==='closed')
                    <x-admin.complete-maintenance-modal :maintenanceRecord="$maintenanceRecord" />
                @endif
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
        <x-admin.delete-modal type="maintenanceRecord" :object="$maintenanceRecord" />

        <div class="modal fade" id="completeMaintenanceModal-{{ $maintenanceRecord->id }}" tabindex="-1"
            aria-labelledby="completeMaintenanceModalLabel-{{ $maintenanceRecord->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.maintenancerecords.complete', $maintenanceRecord->id) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="modal-header">
                            <h5 class="modal-title" id="completeMaintenanceModalLabel-{{ $maintenanceRecord->id }}">
                                Completa intervento
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                        </div>

                        <div class="modal-body">
                            <p class="mb-3">Confermi il completamento in data odierna?</p>
                            <p class="mb-2"><strong>Il guasto è stato aggiustato?</strong></p>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="issue_resolved"
                                    id="issue_resolved_yes_{{ $maintenanceRecord->id }}" value="1" required>
                                <label class="form-check-label" for="issue_resolved_yes_{{ $maintenanceRecord->id }}">
                                    Sì
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="issue_resolved"
                                    id="issue_resolved_no_{{ $maintenanceRecord->id }}" value="0" required>
                                <label class="form-check-label" for="issue_resolved_no_{{ $maintenanceRecord->id }}">
                                    No
                                </label>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                            <button type="submit" class="btn btn-success">Conferma</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
