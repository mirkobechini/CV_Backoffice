@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Il tuo account')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Il tuo account') }}</h1>
    </div>

    <div class="admin-card" style="margin-bottom:16px;">
        @include('profile.partials.update-profile-information-form')
    </div>

    <div class="admin-card" style="margin-bottom:16px;">
        @include('profile.partials.update-password-form')
    </div>

    {{-- Gestione token API personali (Sanctum): nascosta perché al momento
    nessun client usa token creati manualmente da qui — l'app mobile ottiene
    il proprio token tramite POST /api/login. Riattivare includendo di nuovo
    profile.partials.api-tokens-form se in futuro servirà creare token a mano
    (es. integrazioni di terze parti). --}}

    <div class="admin-card">
        @include('profile.partials.delete-user-form')
    </div>
@endsection
