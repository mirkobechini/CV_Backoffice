@props(['openIssues'])

<div class="dash-card">
    <div class="head">
        <h3><span class="ic" style="background:var(--red-soft);color:var(--red)"><i
                    class="fa-solid fa-triangle-exclamation"></i></span>{{ __('Guasti aperti') }}</h3>
    </div>
    <div class="body scrollable">
        @forelse ($openIssues as $issue)
            <a href="{{ route('admin.issues.show', $issue->id) }}" class="dash-list-item">
                <div>
                    <div class="name">{{ $issue->description }}</div>
                    <div class="meta">{{ $issue->vehicle->internal_code }} ·
                        {{ $issue->event_date_formatted }}</div>
                </div>
                <x-admin.status-badge :color="$issue->status_color">{{ $issue->status_label }}</x-admin.status-badge>
            </a>
        @empty
            <div class="dash-empty">
                <div>
                    <div class="name">{{ __('Nessun guasto aperto') }}</div>
                    <div class="meta">{{ __('Tutti i veicoli sono funzionanti') }}</div>
                </div>
            </div>
        @endforelse
        @if ($openIssues->isNotEmpty())
            <a href="{{ route('admin.issues.index') }}" class="dash-see-all">{{ __('Vedi tutti i guasti') }}
                ›</a>
        @endif
    </div>
</div>
