@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => 'Associazioni'],
    ]" />
@endsection

@section('content')
    <div class="page-header">
        <h1>{{ __('Associazioni') }}</h1>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Nome') }}</th>
                        <th>{{ __('Veicoli') }}</th>
                        <th>{{ __('Utenti') }}</th>
                        <th>{{ __('Licenza') }}</th>
                        <th>{{ __('Limiti') }}</th>
                        <th>{{ __('Scadenza') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group)
                        @php($license = $group->latestLicense)
                        <tr>
                            <td class="code">{{ $group->name }}</td>
                            <td>{{ $group->vehicles_count }}</td>
                            <td>{{ $group->users_count }}</td>
                            <td>
                                @if ($license)
                                    <span class="badge text-bg-secondary">{{ ucfirst($license->tier) }}</span>
                                    @if ($license->isExpired())
                                        <span class="badge text-bg-danger">{{ __('Scaduta') }}</span>
                                    @endif
                                @else
                                    <span class="hint">{{ __('Nessuna licenza') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($license)
                                    {{ $license->max_vehicles ?? '∞' }} {{ __('veicoli') }},
                                    {{ $license->max_users ?? '∞' }} {{ __('utenti') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $license?->expires_at?->format('d/m/Y') ?? __('Senza scadenza') }}</td>
                            <td>
                                <a href="{{ route('superadmin.licenses.create', $group) }}" class="btn sm">
                                    {{ $license ? __('Rinnova') : __('Assegna licenza') }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
