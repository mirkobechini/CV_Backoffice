@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Flotta')],
        ['label' => __('Carburante'), 'url' => route('admin.fuel-logs.index')],
        ['label' => __('Nuovo rifornimento')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuovo rifornimento') }}</h1>
        <a href="{{ request('back', route('admin.fuel-logs.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    @include('admin.fuel-logs._form', ['fuelLog' => null])
@endsection
