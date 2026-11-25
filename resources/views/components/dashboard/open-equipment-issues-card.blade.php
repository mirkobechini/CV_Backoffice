@props(['openEquipmentIssues'])

<div class="dash-card">
    <div class="head">
        <h3><span class="ic" style="background:var(--red-soft);color:var(--red)"><i
                    class="fa-solid fa-triangle-exclamation"></i></span>{{ __('Guasti attrezzature aperti') }}</h3>
    </div>
    <div class="body scrollable">
        @forelse ($openEquipmentIssues as $issue)
            <a href="{{ route('admin.equipment-issues.show', $issue->id) }}" class="dash-list-item">
                <div>
                    <div class="name">{{ $issue->description }}</div>
                    <div class="meta">{{ $issue->equipment->name ?: 'N/A' }} ·
                        {{ $issue->equipment->vehicle?->internal_code ?? __('Non assegnata') }} ·
                        {{ $issue->event_date_formatted }}</div>
                </div>
                <x-admin.status-badge :color="$issue->status_color">{{ $issue->status_label }}</x-admin.status-badge>
            </a>
        @empty
            <div class="dash-empty">
                <div>
                    <div class="name">{{ __('Nessun guasto aperto') }}</div>
                    <div class="meta">{{ __('Tutte le attrezzature sono funzionanti') }}</div>
                </div>
            </div>
        @endforelse
        @if ($openEquipmentIssues->isNotEmpty())
            <a href="{{ route('admin.equipment-issues.index') }}"
                class="dash-see-all">{{ __('Vedi tutti i guasti attrezzature') }} ›</a>
        @endif
    </div>
</div>
