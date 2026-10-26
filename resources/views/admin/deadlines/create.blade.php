@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Scadenze'), 'url' => route('admin.deadlines.index')],
        ['label' => __('Nuova scadenza')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Aggiungi nuova scadenza') }}</h1>
        <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    @include('admin.deadlines._form', ['deadline' => null])
@endsection
