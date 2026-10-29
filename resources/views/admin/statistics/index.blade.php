@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Report')],
        ['label' => __('Statistiche')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Statistiche') }}</h1>
        <div class="filters">
            <a href="{{ route('admin.statistics.index', ['tab' => $tab, 'period' => 'all']) }}"
                class="chip {{ $period === 'all' ? 'on' : '' }}">{{ __('Tutto') }}</a>
            <a href="{{ route('admin.statistics.index', ['tab' => $tab, 'period' => '12m']) }}"
                class="chip {{ $period === '12m' ? 'on' : '' }}">{{ __('Ultimo anno') }}</a>
            <a href="{{ route('admin.statistics.index', ['tab' => $tab, 'period' => '6m']) }}"
                class="chip {{ $period === '6m' ? 'on' : '' }}">{{ __('Ultimi 6 mesi') }}</a>
        </div>
    </div>

    <div class="filters" style="margin-bottom:16px;">
        <a href="{{ route('admin.statistics.index', ['tab' => 'guasti', 'period' => $period]) }}"
            class="chip {{ $tab === 'guasti' ? 'on' : '' }}">{{ __('Guasti') }}</a>
        <a href="{{ route('admin.statistics.index', ['tab' => 'costi', 'period' => $period]) }}"
            class="chip {{ $tab === 'costi' ? 'on' : '' }}">{{ __('Costi') }}</a>
        <a href="{{ route('admin.statistics.index', ['tab' => 'carburante', 'period' => $period]) }}"
            class="chip {{ $tab === 'carburante' ? 'on' : '' }}">{{ __('Carburante') }}</a>
        <a href="{{ route('admin.statistics.index', ['tab' => 'pneumatici', 'period' => $period]) }}"
            class="chip {{ $tab === 'pneumatici' ? 'on' : '' }}">{{ __('Pneumatici') }}</a>
        <a href="{{ route('admin.statistics.index', ['tab' => 'attrezzature', 'period' => $period]) }}"
            class="chip {{ $tab === 'attrezzature' ? 'on' : '' }}">{{ __('Attrezzature') }}</a>
        <a href="{{ route('admin.statistics.index', ['tab' => 'scadenze', 'period' => $period]) }}"
            class="chip {{ $tab === 'scadenze' ? 'on' : '' }}">{{ __('Scadenze') }}</a>
        <a href="{{ route('admin.statistics.index', ['tab' => 'affidabilita', 'period' => $period]) }}"
            class="chip {{ $tab === 'affidabilita' ? 'on' : '' }}">{{ __('Affidabilità e utilizzo') }}</a>
    </div>

    @if ($tab === 'costi')
        @include('admin.statistics._tab-costi')
    @elseif ($tab === 'carburante')
        @include('admin.statistics._tab-carburante')
    @elseif ($tab === 'pneumatici')
        @include('admin.statistics._tab-pneumatici')
    @elseif ($tab === 'attrezzature')
        @include('admin.statistics._tab-attrezzature')
    @elseif ($tab === 'scadenze')
        @include('admin.statistics._tab-scadenze')
    @elseif ($tab === 'affidabilita')
        @include('admin.statistics._tab-affidabilita')
    @else
        @include('admin.statistics._tab-guasti')
    @endif
@endsection
