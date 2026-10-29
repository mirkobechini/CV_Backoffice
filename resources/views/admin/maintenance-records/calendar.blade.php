@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Appuntamenti'), 'url' => route('admin.maintenance-records.index')],
        ['label' => __('Calendario')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Calendario Appuntamenti') }}</h1>
        <a href="{{ route('admin.maintenance-records.index') }}" class="btn">
            <i class="fa-solid fa-list"></i> {{ __('Vista lista') }}
        </a>
    </div>

    <div class="cal-card" id="calendar-container">
        <div id="calendar" data-events-url="{{ route('admin.maintenance-records.events') }}"></div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/js/maintenance-calendar.js'])
@endpush
