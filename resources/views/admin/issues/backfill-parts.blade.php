@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Manutenzione')],
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

    @if ($issues->isEmpty())
        <div class="table-card">
            <div class="body" style="padding:24px;">
                <p class="hint" style="margin:0;">{{ __('Tutti i guasti hanno già una parte assegnata. Non c\'è altro da allineare.') }}</p>
            </div>
        </div>
    @else
        <div class="table-card" style="margin-bottom:16px;">
            <div class="body" style="padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                <p class="hint" id="backfill-hint" style="margin:0;">
                    {{ trans_choice('{1} :count guasto senza parte assegnata.|[2,*] :count guasti senza parte assegnata.', $issues->count(), ['count' => $issues->count()]) }}
                    {{ __('Il suggerimento AI va sempre verificato prima di salvare.') }}
                </p>
                <button type="button" id="generate-suggestions-btn" class="btn">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Genera suggerimenti AI') }}
                </button>
            </div>
            <div id="backfill-error" class="alert danger" style="display:none;margin:0 20px 16px;"></div>
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
                                <tr data-issue-id="{{ $issue->id }}">
                                    <td class="code">{{ $issue->vehicle?->internal_code ?? 'N/A' }}</td>
                                    <td>{{ $issue->description }}</td>
                                    <td>{{ $issue->event_date_formatted ?? 'N/A' }}</td>
                                    <td>
                                        <select class="select @error("parts.{$issue->id}") is-invalid @enderror"
                                            id="part-select-{{ $issue->id }}" name="parts[{{ $issue->id }}]">
                                            <option value="">{{ __('Non assegnata') }}</option>
                                            @foreach (\App\Models\Issue::sortedParts() as $part)
                                                <option value="{{ $part }}" {{ old("parts.{$issue->id}") === $part ? 'selected' : '' }}>
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

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const button = document.getElementById('generate-suggestions-btn');
                if (!button) return;

                const hint = document.getElementById('backfill-hint');
                const errorBox = document.getElementById('backfill-error');
                const hintOriginalText = hint.textContent;
                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                const BATCH_SIZE = 25;

                const issueIds = Array.from(document.querySelectorAll('tr[data-issue-id]'))
                    .map(row => parseInt(row.dataset.issueId, 10));

                const chunk = (arr, size) => {
                    const out = [];
                    for (let i = 0; i < arr.length; i += size) {
                        out.push(arr.slice(i, i + size));
                    }
                    return out;
                };

                button.addEventListener('click', async function() {
                    button.disabled = true;
                    errorBox.style.display = 'none';
                    const batches = chunk(issueIds, BATCH_SIZE);
                    let done = 0;

                    for (const batch of batches) {
                        hint.textContent = `{{ __('Analisi in corso...') }} (${done}/${issueIds.length})`;

                        try {
                            const response = await fetch('{{ route('admin.issues.backfill-parts.suggest') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({
                                    issue_ids: batch
                                }),
                            });

                            if (!response.ok) {
                                const body = await response.json().catch(() => ({}));
                                throw new Error(body.message || `HTTP ${response.status}`);
                            }

                            const data = await response.json();
                            Object.entries(data.suggestions || {}).forEach(([issueId, part]) => {
                                if (!part) return;
                                const select = document.getElementById(`part-select-${issueId}`);
                                if (select) select.value = part;
                            });
                        } catch (err) {
                            errorBox.textContent = `{{ __('Impossibile generare i suggerimenti per un blocco di guasti:') }} ${err.message}`;
                            errorBox.style.display = '';
                            break;
                        }

                        done += batch.length;
                    }

                    hint.textContent = hintOriginalText;
                    button.disabled = false;
                });
            });
        </script>
    @endpush
@endsection
