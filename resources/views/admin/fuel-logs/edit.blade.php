@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Carburante'), 'url' => route('admin.fuel-logs.index')],
        ['label' => $fuelLog->vehicle->internal_code ?? 'N/A', 'url' => route('admin.fuel-logs.show', $fuelLog->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica rifornimento') }}</h1>
        <a href="{{ request('back', route('admin.fuel-logs.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    @include('admin.fuel-logs._form', ['fuelLog' => $fuelLog])
@endsection
