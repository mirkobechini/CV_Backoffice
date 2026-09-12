@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Gruppi')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Gruppi') }}</h1>
        <a href="{{ route('admin.groups.create') }}" class="btn primary">
            <i class="fa-solid fa-plus"></i> {{ __('Nuovo gruppo') }}
        </a>
    </div>

    <div class="join-card">
        <h3><span class="ic"><i class="fa-solid fa-key"></i></span> {{ __('Entra in un gruppo esistente') }}</h3>
        <form method="POST" action="{{ route('admin.groups.join') }}" class="join-row">
            @csrf
            <input type="text" name="invite_code" class="input" placeholder="{{ __('Codice invito') }}" maxlength="12"
                required>
            <button type="submit" class="btn outline">{{ __('Entra') }}</button>
        </form>
        @error('invite_code')
            <div class="field-error" style="margin-top:8px;">{{ $message }}</div>
        @enderror
    </div>

    @if ($groups->isEmpty())
        <div class="empty">{{ __('Non appartieni ancora a nessun gruppo.') }}</div>
    @else
        <div class="groups-grid">
            @foreach ($groups as $group)
                <a href="{{ route('admin.groups.show', $group) }}" class="g-card"
                    style="text-decoration:none; display:block;">
                    <div class="g-avatar"><i class="fa-solid fa-users"></i></div>
                    <div class="g-name">{{ $group->name }}</div>
                    <div class="g-meta">
                        {{ __(':users membri · :vehicles veicoli', ['users' => $group->users_count, 'vehicles' => $group->vehicles_count]) }}
                    </div>
                    <span class="g-btn">{{ __('Gestisci') }} ›</span>
                </a>
            @endforeach
        </div>
    @endif
@endsection
