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
                            class="fa-solid fa-user"></i></span> {{ __('Account') }}</h3>
            </div>
            <div class="body">
                <p class="hint" style="margin-bottom:10px;">
                    {{ __('Nome, email, password e altre impostazioni personali del tuo account.') }}</p>
                <a href="{{ route('profile.edit') }}" class="btn outline">
                    <i class="fa-solid fa-arrow-right"></i> {{ __('Vai al tuo account') }}
                </a>
                <p class="hint" style="margin-top:14px;">
                    {{ __('Per rinominare un gruppo, invitare membri o gestirne i ruoli, apri la pagina del gruppo da') }}
                    <a href="{{ route('admin.groups.index') }}">{{ __('Gruppi') }}</a>.
                </p>
            </div>
        </div>

        @if ($canManageBackups)
            <div class="admin-card">
                <div class="head">
                    <h3><span class="ic" style="background:var(--blue-soft); color:var(--blue);"><i
                                class="fa-solid fa-database"></i></span> {{ __('Backup database') }}</h3>
                </div>
                <div class="body">
                    <p class="hint" style="margin-bottom:10px;">
                        {{ __('Crea un backup del database in formato JSON.') }}</p>
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
        @endif
    </div>
@endsection
