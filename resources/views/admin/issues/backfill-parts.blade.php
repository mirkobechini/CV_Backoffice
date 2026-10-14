@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Guasti'), 'url' => route('admin.issues.index')],
        ['label' => __('Allinea parte veicolo')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Allinea parte veicolo sui guasti esistenti') }}</h1>
        <a href="{{ route('admin.issues.index') }}" class="btn ghost">
            <i class="fa-solid fa-arrow-left"></i> {{ __('Torna ai guasti') }}
        </a>
    </div>

    @if (session('status'))
        <div class="alert success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert danger">{{ session('error') }}</div>
    @endif

    @if ($issues->isEmpty())
        <div class="table-card">
            <div class="body" style="padding:24px;">
                <p class="hint" style="margin:0;">{{ __('Tutti i guasti hanno già una parte assegnata. Non c\'è altro da allineare.') }}</p>
            </div>
        </div>
    @else
        @php($suggestions = session('part_suggestions', []))

        <div class="table-card" style="margin-bottom:16px;">
            <div class="body" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <p class="hint" style="margin:0;">
                    {{ trans_choice('{1} :count guasto senza parte assegnata.|[2,*] :count guasti senza parte assegnata.', $issues->count(), ['count' => $issues->count()]) }}
                    {{ __('Il suggerimento AI va sempre verificato prima di salvare.') }}
                </p>
                <form method="POST" action="{{ route('admin.issues.backfill-parts.suggest') }}" data-single-submit="true">
                    @csrf
                    <button type="submit" class="btn" data-loading-text="{{ __('Genero suggerimenti...') }}">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Genera suggerimenti AI') }}
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.issues.backfill-parts.store') }}" data-single-submit="true">
            @csrf
            <div class="table-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>{{ __('Veicolo') }}</th>
                                <th>{{ __('Descrizione') }}</th>
                                <th>{{ __('Data') }}</th>
                                <th>{{ __('Parte') }} <span class="req">*</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($issues as $issue)
                                @php($suggested = old("parts.{$issue->id}", $suggestions[$issue->id] ?? null))
                                <tr>
                                    <td class="code">{{ $issue->vehicle?->internal_code ?? 'N/A' }}</td>
                                    <td>{{ $issue->description }}</td>
                                    <td>{{ $issue->event_date_formatted ?? 'N/A' }}</td>
                                    <td>
                                        <select class="select @error("parts.{$issue->id}") is-invalid @enderror"
                                            name="parts[{{ $issue->id }}]">
                                            <option value="">{{ __('Non assegnata') }}</option>
                                            @foreach (\App\Models\Issue::PARTS as $part)
                                                <option value="{{ $part }}" {{ $suggested === $part ? 'selected' : '' }}>
                                                    {{ $part }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error("parts.{$issue->id}")
                                            <div class="field-error">{{ $message }}</div>
                                        @enderror
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="form-actions" style="margin-top:16px;">
                <button type="submit" class="btn primary lg" data-loading-text="{{ __('Salvataggio...') }}">
                    <i class="fa-solid fa-check"></i> {{ __('Salva le parti assegnate') }}
                </button>
            </div>
        </form>
    @endif
@endsection
