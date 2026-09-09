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
                    @php
                        $issueDescriptions = $maintenanceRecord->items
                            ->where('itemable_type', 'App\\Models\\Issue')
                            ->map(fn($item) => $item->itemable?->description)
                            ->filter()
                            ->implode(', ');
                        $title =
                            $issueDescriptions !== ''
                                ? $issueDescriptions
                                : $maintenanceRecord->activity_type ?? 'Intervento';
                    @endphp
                    <div class="card-header">
                        <h1>{{ $title }}</h1>
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

                        @php
                            $linkedIssues = $maintenanceRecord->items
                                ->where('itemable_type', 'App\\Models\\Issue')
                                ->map(fn($item) => $item->itemable)
                                ->filter();
                            $linkedDeadlines = $maintenanceRecord->items
                                ->where('itemable_type', 'App\\Models\\Deadline')
                                ->map(fn($item) => $item->itemable)
                                ->filter();
                        @endphp

                        @if ($linkedIssues->isNotEmpty())
                            <hr>
                            <h6>Guasti collegati</h6>
                            <ul class="mb-0">
                                @foreach ($linkedIssues as $issue)
                                    <li>
                                        {{ $issue->description }}
                                        @if ($issue->event_date)
                                            — {{ $issue->event_date->format('d/m/Y') }}
                                        @endif
                                        <span
                                            class="badge {{ match ($issue->status_color) {'red' => 'bg-danger text-light','yellow' => 'bg-warning text-dark','green' => 'bg-success',default => 'bg-secondary'} }} ms-1">{{ $issue->status_label }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($linkedDeadlines->isNotEmpty())
                            <hr>
                            <h6>Scadenze collegate</h6>
                            <ul class="mb-0">
                                @foreach ($linkedDeadlines as $deadline)
                                    <li>
                                        {{ $deadline->type }}
                                        @if ($deadline->due_date)
                                            — {{ $deadline->due_date->format('d/m/Y') }}
                                        @endif
                                        <span
                                            class="badge {{ match ($deadline->status_color) {'red' => 'bg-danger text-light','yellow' => 'bg-warning text-dark','green' => 'bg-success',default => 'bg-secondary'} }} ms-1">{{ $deadline->status_label }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-12">
                @if (
                    $maintenanceRecord->return_date === null &&
                        $maintenanceRecord->items->where('itemable_type', 'App\\Models\\Issue')->first()?->itemable?->status !==
                            'closed')
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
