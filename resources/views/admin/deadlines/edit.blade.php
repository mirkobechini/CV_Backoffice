@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Scadenze'), 'url' => route('admin.deadlines.index')],
        ['label' => $deadline->type, 'url' => route('admin.deadlines.show', $deadline->id)],
        ['label' => __('Modifica')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Modifica scadenza') }}</h1>
        <a href="{{ request('back', route('admin.deadlines.index')) }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Annulla') }}
        </a>
    </div>

    @if ($deadline->is_renewed && $deadline->renewedByDeadline)
        <div class="alert warning" style="margin-bottom:16px;">
            {{ __('Stai modificando la scadenza VECCHIA, già rinnovata (:d).', ['d' => $deadline->due_date_formatted ?? __('senza data')]) }}
            <a href="{{ route('admin.deadlines.edit', $deadline->renewedByDeadline) }}">{{ __('Modifica invece quella nuova') }}
                ›</a>
        </div>
    @elseif ($deadline->renewsDeadline)
        <div class="alert info" style="margin-bottom:16px;">
            {{ __('Stai modificando la scadenza di RINNOVO di quella precedente (:d).', ['d' => $deadline->renewsDeadline->due_date_formatted ?? __('senza data')]) }}
            <a href="{{ route('admin.deadlines.edit', $deadline->renewsDeadline) }}">{{ __('Modifica invece quella vecchia') }}
                ›</a>
        </div>
    @endif

    @include('admin.deadlines._form', ['deadline' => $deadline])
@endsection
