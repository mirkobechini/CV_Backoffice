@extends('layouts.superadmin')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Codici sconto')],
    ]" />
@endsection

@section('content')
    <div class="page-header">
        <h1>{{ __('Codici sconto') }}</h1>
        <a href="{{ route('superadmin.discount-codes.create') }}" class="btn primary">
            <i class="fa-solid fa-plus"></i> {{ __('Nuovo codice') }}
        </a>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Codice') }}</th>
                        <th>{{ __('Sconto') }}</th>
                        <th>{{ __('Usi') }}</th>
                        <th>{{ __('Scadenza') }}</th>
                        <th>{{ __('Stato') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($discountCodes as $code)
                        <tr>
                            <td class="code">{{ $code->code }}</td>
                            <td>{{ $code->percent }}%</td>
                            <td>{{ $code->used_count }}{{ $code->max_uses ? ' / '.$code->max_uses : '' }}</td>
                            <td>{{ $code->expires_at?->format('d/m/Y') ?? __('Senza scadenza') }}</td>
                            <td>
                                @if ($code->active && $code->isUsable())
                                    <span class="badge text-bg-success">{{ __('Attivo') }}</span>
                                @elseif ($code->active)
                                    <span class="badge text-bg-warning">{{ __('Esaurito/scaduto') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Disattivato') }}</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('superadmin.discount-codes.toggle', $code) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn sm">
                                        {{ $code->active ? __('Disattiva') : __('Riattiva') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <p class="hint" style="margin:0;">{{ __('Nessun codice sconto creato.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
