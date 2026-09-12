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

    <div class="admin-card" style="margin-bottom:16px;">
        @include('profile.partials.api-tokens-form')
    </div>

    <div class="admin-card">
        @include('profile.partials.delete-user-form')
    </div>
@endsection
