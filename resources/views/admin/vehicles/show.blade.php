@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="row mb-3">
            <div class="col-12">
                <a href="{{ route('admin.vehicles.index') }}" class="btn btn-secondary">Torna alla lista</a>
            </div>
        </div>
        <div class="row">
            <div class="col-3 align-middle text-center">
                <h1 class="display-1 fw-bold text-center align-middle pt-3">{{ $vehicle->internal_code }}</h1>
                <h5>{{ $vehicle->vehicleType->name ?? 'N/A' }}</h5>

            </div>
            <div class="col-8">
                <div class="card border-0 mb-3">
                    <div class="card-body">
                        <div class="row row-cols-1 row-cols-md-3">
                            <div class="col">
                                <span class="card-text d-block"><strong>Targa:</strong> {{ $vehicle->license_plate }}</span>
                                <span class="card-text d-block"><strong>Marca:</strong> {{ $vehicle->brand }}</span>
                                <span class="card-text d-block"><strong>Modello:</strong> {{ $vehicle->model }}</span>
                                <span class="card-text d-block"><strong>Carburante:</strong>
                                    {{ $vehicle->fuel_type }}</span>
                                <span class="card-text d-block"><strong>Chilometri:</strong> {{ $vehicle->mileage }}</span>
                            </div>
                            <div class="col">
                                <span class="card-text d-block"><strong>Data immatricolazione:</strong>
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
                            <div class="col">
                                <span class="card-text d-block"><strong>Revisione:</strong> {{ $vehicle->revision }}</span>
                                <span class="card-text d-block"><strong>Tagliando:</strong> {{ $vehicle->service }}</span>
                                <span class="card-text d-block"><strong>Assicurazione:</strong>
                                    {{ $vehicle->insurance }}</span>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 mb-3">
                <h2 class="display-6 fw-bold">Guasti<a class="btn btn-success rounded-pill ms-3 py-0 px-2"
                        href="{{ route('admin.issues.create', ['vehicle_id' => $vehicle->id]) }}"><i
                            class="fa-solid fa-add text-light"></i></a>
                </h2>
                @if ($vehicle->issues->isEmpty())
                    <p class="card-text">Nessun guasto registrato per questo veicolo.</p>
                @else
                    <ul class="list-group">
                        @foreach ($vehicle->issues as $issue)
                            <li
                                class="list-group-item @if ($issue->status === 'open') list-group-item-danger @elseif($issue->status === 'in_progress') list-group-item-warning @else list-group-item-success @endif">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Data:</strong>
                                        {{ $issue->event_date_formatted ?? 'N/A' }}<br>
                                        <strong>Descrizione:</strong> {{ $issue->description }}<br>
                                    </div>
                                    @if ($issue->status != 'closed')
                                        <div class="col-6">
                                            <h5>Officina</h5>
                                            <p class="card-text">Da contattare</p>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection
