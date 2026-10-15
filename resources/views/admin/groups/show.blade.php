@extends('layouts.app')

@php
    $isCapo = auth()->user()->roleIn($group) === 'capo';
    $canManageInvites = $group->userCanManageInvites(auth()->user());
    $canSeeTireSeason = $group->userCanSeeTireSeason(auth()->user());
@endphp

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Gruppi'), 'url' => route('admin.groups.index')],
        ['label' => $group->name],
    ]" />
@endsection

@section('content')

    <div class="page-actions">
        <a href="{{ route('admin.groups.index') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna ai gruppi') }}
        </a>
    </div>

    <div class="g-header">
        <div class="g-avatar"><i class="fa-solid fa-users"></i></div>
        <div class="g-title">
            <h1>{{ $group->name }}</h1>
            <div class="sub">
                {{ __(':users membri · :vehicles veicoli', ['users' => $group->users->count(), 'vehicles' => $group->vehicles->count()]) }}
            </div>
        </div>
    </div>

    @error('delete_vehicles')
        <div class="field-error" style="margin-top:14px;">{{ $message }}</div>
    @enderror

    <div class="filters" style="margin:16px 0;">
        <a href="{{ route('admin.groups.show', $group) }}"
            class="chip {{ $tab === 'panoramica' ? 'on' : '' }}">{{ __('Panoramica') }}</a>
        @if ($canSeeSettings)
            <a href="{{ route('admin.groups.show', ['group' => $group, 'tab' => 'impostazioni']) }}"
                class="chip {{ $tab === 'impostazioni' ? 'on' : '' }}">{{ __('Impostazioni') }}</a>
        @endif
    </div>

    @if ($tab === 'impostazioni')
        @include('admin.groups._tab-impostazioni')
    @else
        @include('admin.groups._tab-panoramica')
    @endif

@endsection
