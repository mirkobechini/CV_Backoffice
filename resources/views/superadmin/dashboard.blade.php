@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => 'Associazioni'],
    ]" />
@endsection

@section('content')
    <div class="page-header">
        <h1>{{ __('Associazioni') }}</h1>
        <a href="{{ route('superadmin.groups.create') }}" class="btn primary">
            <i class="fa-solid fa-plus"></i> {{ __('Nuova associazione') }}
        </a>
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
                                <button type="button" class="btn danger sm" data-bs-toggle="modal"
                                    data-bs-target="#deleteGroupModal-{{ $group->id }}">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @foreach ($groups as $group)
        <div class="modal fade" id="deleteGroupModal-{{ $group->id }}" tabindex="-1"
            aria-labelledby="deleteGroupModalLabel-{{ $group->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="{{ route('superadmin.groups.destroy', $group) }}">
                        @csrf
                        @method('DELETE')
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="deleteGroupModalLabel-{{ $group->id }}">
                                {{ __('Eliminare ":name"?', ['name' => $group->name]) }}
                            </h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="{{ __('Chiudi') }}"></button>
                        </div>
                        <div class="modal-body">
                            {{ __('Azione pesante: tutti i dati del gruppo smettono di essere accessibili.') }}
                            @if ($group->vehicles_count > 0)
                                <label class="check" style="margin-top:12px;">
                                    <input type="checkbox" name="delete_vehicles" value="1" required>
                                    <div>
                                        <div class="label">
                                            {{ __('Elimina anche i :n veicoli del gruppo', ['n' => $group->vehicles_count]) }}
                                        </div>
                                        <div class="sub">{{ __('Necessario per procedere: il gruppo ha veicoli associati.') }}</div>
                                    </div>
                                </label>
                            @endif
                            <div style="margin-top:14px;">
                                <label for="confirm-group-name-{{ $group->id }}" class="label">
                                    {{ __('Per confermare, scrivi il nome del gruppo: ":name"', ['name' => $group->name]) }}
                                </label>
                                <input type="text" id="confirm-group-name-{{ $group->id }}" name="confirm_name"
                                    class="form-control" autocomplete="off" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Annulla') }}</button>
                            <button type="submit" class="btn btn-danger" id="confirm-delete-group-btn-{{ $group->id }}"
                                disabled>{{ __('Elimina gruppo') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @foreach ($groups as $group)
                    (function() {
                        const input = document.getElementById('confirm-group-name-{{ $group->id }}');
                        const btn = document.getElementById('confirm-delete-group-btn-{{ $group->id }}');
                        const expected = @json($group->name);
                        input.addEventListener('input', () => {
                            btn.disabled = input.value !== expected;
                        });
                    })();
                @endforeach
            });
        </script>
    @endpush
@endsection
