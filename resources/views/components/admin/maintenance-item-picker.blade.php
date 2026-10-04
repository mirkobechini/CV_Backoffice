@props([
    'openIssues',
    'closedIssues',
    'pendingDeadlines',
    'defaultCheckedIssueIds' => [],
    'defaultCheckedDeadlineIds' => [],
    'idPrefix' => '',
])

<div class="field" id="issue-section" style="display:none;">
    <label>{{ __('Guasti collegati') }}</label>
    <div class="check-list" id="issue-checkboxes">
        @error('issue_ids')
            <div class="field-error">{{ $message }}</div>
        @enderror
        @foreach ($openIssues as $issue)
            <div class="item issue-checkbox" data-vehicle-id="{{ $issue->vehicle_id }}" style="display:none;">
                <input type="checkbox" name="issue_ids[]" value="{{ $issue->id }}"
                    id="{{ $idPrefix }}issue_{{ $issue->id }}"
                    {{ in_array((string) $issue->id, old('issue_ids', $defaultCheckedIssueIds)) ? 'checked' : '' }}>
                <label class="lbl" for="{{ $idPrefix }}issue_{{ $issue->id }}">
                    {{ $issue->description }}
                    @if ($issue->event_date)
                        <span class="meta">— {{ $issue->event_date->format('d/m/Y') }}</span>
                    @endif
                    @if ($issue->status !== 'open' && $issue->status !== 'in_progress')
                        <span class="badge b-amber">({{ $issue->status }})</span>
                    @endif
                </label>
            </div>
        @endforeach
        <div class="empty" id="no-issue-msg" style="display:none;">
            {{ __('Nessun guasto aperto per il veicolo selezionato.') }}
        </div>
    </div>
</div>

@if ($closedIssues->isNotEmpty())
    <div class="field" id="closed-issue-section" style="display:none;">
        <label>{{ __('Guasti risolti (per registrare riparazioni avvenute)') }}</label>
        <div class="check-list" id="closed-issue-checkboxes">
            @foreach ($closedIssues as $issue)
                <div class="item closed-issue-checkbox" data-vehicle-id="{{ $issue->vehicle_id }}" style="display:none;">
                    <input type="checkbox" name="issue_ids[]" value="{{ $issue->id }}"
                        id="{{ $idPrefix }}closed_issue_{{ $issue->id }}">
                    <label class="lbl" for="{{ $idPrefix }}closed_issue_{{ $issue->id }}">
                        {{ $issue->description }}
                        @if ($issue->event_date)
                            <span class="meta">— {{ $issue->event_date->format('d/m/Y') }}</span>
                        @endif
                    </label>
                </div>
            @endforeach
            <div class="empty" id="no-closed-issue-msg" style="display:none;">
                {{ __('Nessun guasto risolto per il veicolo selezionato.') }}
            </div>
        </div>
    </div>
@endif

<div class="field" id="deadline-section" style="display:none;">
    <label>{{ __('Scadenze collegate') }}</label>
    <div class="check-list" id="deadline-checkboxes">
        @error('deadline_ids')
            <div class="field-error">{{ $message }}</div>
        @enderror
        @foreach ($pendingDeadlines as $deadline)
            <div class="item deadline-checkbox" data-vehicle-id="{{ $deadline->vehicle_id }}" style="display:none;">
                <input type="checkbox" name="deadline_ids[]" value="{{ $deadline->id }}"
                    id="{{ $idPrefix }}deadline_{{ $deadline->id }}"
                    {{ in_array((string) $deadline->id, old('deadline_ids', $defaultCheckedDeadlineIds)) ? 'checked' : '' }}>
                <label class="lbl" for="{{ $idPrefix }}deadline_{{ $deadline->id }}">
                    {{ ucfirst($deadline->type) }} —
                    {{ $deadline->due_date?->format('d/m/Y') ?? 'N/A' }}
                    <span class="meta">({{ $deadline->days_label }})</span>
                    <span
                        class="badge {{ match ($deadline->status_color) {
                            'red' => 'b-red',
                            'yellow' => 'b-amber',
                            'green' => 'b-green',
                            default => 'b-gray',
                        } }}">{{ $deadline->status_label }}</span>
                </label>
            </div>
        @endforeach
        <div class="empty" id="no-deadline-msg" style="display:none;">
            {{ __('Nessuna scadenza in sospeso per il veicolo selezionato.') }}
        </div>
    </div>
</div>

<div class="field" id="no-issue-cta" style="display:none;">
    <div class="check-list" style="display:flex; align-items:center; justify-content:space-between; gap:10px;">
        <span class="hint" style="margin:0;">{{ __('Nessun guasto aperto per il veicolo selezionato.') }}</span>
        <a id="create-issue-link" class="btn primary"
            href="{{ route('admin.issues.create', ['back' => url()->full()]) }}">
            {{ __('Crea guasto') }}
        </a>
    </div>
</div>
