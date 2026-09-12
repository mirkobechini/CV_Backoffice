@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Impostazioni')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Impostazioni') }}</h1>
    </div>

    <div class="row2">
        <div class="admin-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--primary-soft); color:var(--primary);"><i
                            class="fa-solid fa-users"></i></span> {{ __('Associazione / Gruppo') }}</h3>
            </div>
            <div class="body">
                @if ($group)
                    @if (auth()->user()->roleIn($group) === 'capo')
                        <form method="POST" action="{{ route('admin.settings.group') }}">
                            @csrf
                            @method('PATCH')
                            <div class="field">
                                <label for="name">{{ __('Nome') }}</label>
                                <input type="text" class="input @error('name') is-invalid @enderror" id="name"
                                    name="name" value="{{ old('name', $group->name) }}" required>
                                @error('name')
                                    <div class="field-error">{{ $message }}</div>
                                @enderror
                            </div>
                            <button type="submit" class="btn primary">{{ __('Salva') }}</button>
                        </form>
                    @else
                        <p style="font-weight:600;">{{ $group->name }}</p>
                        <p class="hint" style="margin-top:4px;">
                            {{ __('Solo il capo può modificare le impostazioni del gruppo.') }}</p>
                    @endif
                @else
                    <p class="hint">{{ __('Non appartieni a nessun gruppo.') }}</p>
                @endif
            </div>
        </div>

        <div class="admin-card">
            <div class="head">
                <h3><span class="ic" style="background:var(--blue-soft); color:var(--blue);"><i
                            class="fa-solid fa-database"></i></span> {{ __('Backup database') }}</h3>
            </div>
            <div class="body">
                <p class="hint" style="margin-bottom:10px;">{{ __('Crea un backup del database in formato JSON.') }}</p>
                <form method="POST" action="{{ route('admin.settings.backup') }}" data-single-submit="true">
                    @csrf
                    <button type="submit" class="btn outline" data-loading-text="{{ __('Creazione...') }}">
                        <i class="fa-solid fa-floppy-disk"></i> {{ __('Crea backup') }}
                    </button>
                </form>

                @if ($backups->isNotEmpty())
                    <div class="backup-list">
                        <h4>{{ __('Backup recenti') }}</h4>
                        @foreach ($backups as $backup)
                            <div class="b-item">
                                <span class="ic"><i class="fa-solid fa-file-lines"></i></span>
                                <span class="name">{{ $backup['name'] }}</span>
                                <span class="size">{{ round($backup['size'] / 1024, 1) }} KB</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
