@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ request('back', route('admin.vehicles.index')) }}" class="btn btn-secondary">Torna alla pagina
                    precedente</a>
            </div>
        </div>
        <div class="row row-cols-1">
            <div class="col-md-3 align-middle text-center">
                <h1 class="display-1 fw-bold text-center align-middle pt-3">{{ $vehicle->internal_code }}</h1>
                <h5>{{ $vehicle->vehicleType->name ?? 'N/A' }}</h5>

            </div>
            <div class="col-md-8">
                <div class="card border-0 mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-3 mb-4 mb-md-0">
                                <h5 class="mb-0 mb-md-3">Anagrafica</h5>
                                <span class="card-text d-block"><strong>Targa:</strong> {{ $vehicle->license_plate }}</span>
                                <span class="card-text d-block"><strong>Marca:</strong>
                                    {{ $vehicle->brand->name ?? 'N/A' }}</span>
                                <span class="card-text d-block"><strong>Modello:</strong>
                                    {{ $vehicle->carModel->name ?? 'N/A' }}</span>
                                <span class="card-text d-block"><strong>Carburante:</strong>
                                    {{ $vehicle->fuel_type }}</span>
                                <span class="card-text d-block"><strong>Chilometri:</strong> {{ $vehicle->mileage }}</span>
                            </div>
                            <div class="col-12 col-md-4 mb-4 mb-md-0">
                                <h5 class="mb-0 mb-md-3">Documenti</h5>
                                <span class="card-text d-block"><strong>Immatricolazione:</strong>
                                    {{ $vehicle->immatricolation_date_formatted ?? 'N/A' }}</span>
                                <span class="card-text d-block"><strong>Carta di circolazione:</strong>
                                    @if ($vehicle->registration_card_path)
                                        <a href="{{ Storage::url($vehicle->registration_card_path) }}" target="_blank"
                                            rel="noopener noreferrer">Apri file</a>
                                    @else
                                        N/A
                                    @endif
                                </span>

                                <span class="card-text d-block"><strong>Garanzia:</strong>
                                    {{ $vehicle->warranty_expiration_date_formatted ?? 'N/A' }}
                                    {!! $vehicle->is_warranty_expired
                                        ? '<i class="fa-solid fa-times text-danger"></i>'
                                        : '<i class="fa-solid fa-check text-success"></i>' !!}
                                </span>
                            </div>
                            <div class="col-12 col-md-5 mb-4 mb-md-0">
                                <h5 class="mb-0 mb-md-3">Scadenze</h5>
                                <span class="card-text d-block"><strong>Revisione:</strong>
                                    {{ $deadlines->get($deadlinesTypes['revisione'])?->due_date_formatted ?? 'N/A' }}
                                    {!! $deadlines->get($deadlinesTypes['revisione'])?->getAutomaticStatusAttribute() === 'expired'
                                        ? '<i class="fa-solid fa-times text-danger"></i>'
                                        : '<i class="fa-solid fa-check text-success"></i>' !!}
                                </span>
                                @if ($vehicle->vehicleType?->needs_oxygen_check)
                                    <span class="card-text d-block"><strong>Revisione Ossigeno:</strong>
                                        {{ $deadlines->get($deadlinesTypes['ossigeno'])?->due_date_formatted ?? 'N/A' }}
                                        {!! $deadlines->get($deadlinesTypes['ossigeno'])?->getAutomaticStatusAttribute() === 'expired'
                                            ? '<i class="fa-solid fa-times text-danger"></i>'
                                            : '<i class="fa-solid fa-check text-success"></i>' !!}
                                    </span>
                                @endif
                                <span class="card-text d-block"><strong>Tagliando:</strong> </span>
                                <span class="card-text d-block"><strong>Assicurazione:</strong>
                                    {{ $deadlines->get('Assicurazione')?->due_date_formatted ?? 'N/A' }}</span>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <h2 class="display-6 fw-bold">Equipaggiamento<a class="btn btn-success rounded-pill ms-3 py-0 px-2"
                        href="{{ route('admin.equipments.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"><i
                            class="fa-solid fa-add text-light"></i></a>
                </h2>
                @if ($vehicle->equipment->isEmpty())
                    <p class="card-text">Nessun equipaggiamento registrato per questo veicolo.</p>
                @else
                    <ul class="list-group">
                        @foreach ($vehicle->equipment as $equipment)
                            <li class="list-group-item ">
                                <div class="row row-cols-1 row-cols-md-2 justify-content-between align-items-center">
                                    <div class="col-md-10">
                                        <div class="row row-cols-1 row-cols-md-2">
                                            <div class="col">
                                                <p>{{ $equipment->equipmentType->name ?? 'N/A' }} - {{ $equipment->serial_number ?? 'N/A' }}</p>
                                            </div>
                                            <div class="col">
                                                <p>Revisione: {{ $equipment->getExpirationDateFormattedAttribute() ?? 'N/A' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <a class="btn btn-primary rounded-pill "
                                            href="{{ route('admin.equipments.show', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="{{ route('admin.equipments.edit', ['equipment' => $equipment->id, 'back' => url()->full()]) }}"
                                            class="btn btn-secondary rounded-pill "><i class="bi bi-pencil"></i></a>
                                        <button type="button" class="btn btn-danger rounded-pill " data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteModal-{{ $equipment->id }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <x-admin.delete-modal type="equipment" :object="$equipment" />
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <h2 class="display-6 fw-bold">Guasti<a class="btn btn-success rounded-pill ms-3 py-0 px-2"
                        href="{{ route('admin.issues.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"><i
                            class="fa-solid fa-add text-light"></i></a>
                </h2>
                @if ($vehicle->issues->isEmpty())
                    <p class="card-text">Nessun guasto registrato per questo veicolo.</p>
                @else
                    <ul class="list-group">
                        @foreach ($vehicle->issues as $issue)
                            <li
                                class="list-group-item @if ($issue->status === 'open') list-group-item-danger @elseif($issue->status === 'in_progress') list-group-item-warning @else list-group-item-success @endif">
                                <div class="row row-cols-1 row-cols-md-2 justify-content-between align-items-center">
                                    <div class="col">
                                        <div class="row row-cols-1 row-cols-md-2">

                                            <div class="col-md-10">
                                                <strong>Data:</strong>
                                                {{ $issue->event_date_formatted ?? 'N/A' }}<br>
                                                <strong>Descrizione:</strong> {{ $issue->description }}<br>
                                            </div>
                                            @if ($vehicleAppointments->where('issue_id', $issue->id)->where('provider_id', '!=', '')->isNotEmpty())
                                                <div class="col-md-2">
                                                    <h5>Officina</h5>
                                                    @php
                                                        $appointment = $vehicleAppointments
                                                            ->where('issue_id', $issue->id)
                                                            ->where('provider_id', '!=', '')
                                                            ->first();
                                                    @endphp
                                                    <p class="card-text">
                                                        @if ($appointment?->provider)
                                                            <a class="text-decoration-none text-reset"
                                                                href="{{ route('admin.providers.show', ['provider' => $appointment->provider->id, 'back' => url()->full()]) }}">{{ $appointment->provider->name }}</a>
                                                        @else
                                                            N/A
                                                        @endif
                                                    </p>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <a class="btn btn-primary rounded-pill "
                                            href="{{ route('admin.issues.show', ['issue' => $issue->id, 'back' => url()->full()]) }}"><i
                                                class="bi bi-eye"></i></a>
                                        <a href="{{ route('admin.issues.edit', ['issue' => $issue->id, 'back' => url()->full()]) }}"
                                            class="btn btn-secondary rounded-pill "><i class="bi bi-pencil"></i></a>
                                        <button type="button" class="btn btn-danger rounded-pill " data-bs-toggle="modal"
                                            data-bs-target="#confirmDeleteModal-{{ $issue->id }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <x-admin.delete-modal type="issue" :object="$issue" />
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
