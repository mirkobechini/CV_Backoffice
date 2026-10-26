@props(['vehicle', 'issueProviders'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Guasti') }}
            @if ($vehicle->issues->where('status', 'open')->isNotEmpty())
                <span class="count c-red">{{ $vehicle->issues->where('status', 'open')->count() }}</span>
            @endif
            @if ($vehicle->issues->where('status', 'in_progress')->isNotEmpty())
                <span class="count c-amber">{{ $vehicle->issues->where('status', 'in_progress')->count() }}</span>
            @endif
        </h3>
        <a href="{{ route('admin.issues.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"
            class="veh-btn-add" title="{{ __('Nuovo guasto') }}" aria-label="{{ __('Nuovo guasto') }}"><i class="fa-solid fa-plus"></i></a>
    </div>
    <div class="body">
        @php
            $issueStatusClasses = ['open' => 'open', 'in_progress' => 'work'];

            // Guasti aperti/in lavorazione sempre in cima alla card,
            // indipendentemente dalla data: sono quelli che
            // richiedono attenzione. All'interno di ciascun gruppo
            // (aperti+in lavorazione, poi chiusi) restano ordinati
            // per data più recente, grazie alla stabilità di
            // sortBy() sopra un array già ordinato per data.
            $sortedIssues = $vehicle->issues
                ->sortByDesc(fn($issue) => $issue->event_date?->format('Y-m-d') ?? '')
                ->sortBy(fn($issue) => in_array($issue->status, ['open', 'in_progress'], true) ? 0 : 1)
                ->values();
        @endphp
        @forelse ($sortedIssues as $issue)
            @php($issueProvider = $issueProviders->get($issue->id))
            <div class="veh-issue-item {{ $issueStatusClasses[$issue->status] ?? 'done' }}">
                <div>
                    <div class="date">{{ $issue->event_date_formatted ?? 'N/A' }}</div>
                </div>
                <span class="desc">{{ $issue->description }}
                    @if ($issueProvider)
                        <span class="meta">
                            <a href="{{ route('admin.providers.show', ['provider' => $issueProvider->id, 'back' => url()->full()]) }}"
                                style="color:inherit;">{{ $issueProvider->name }}</a>
                        </span>
                    @endif
                </span>
                <x-admin.status-badge :color="$issue->status_color">{{ $issue->status_label }}</x-admin.status-badge>
                <x-admin.row-actions
                    :show-url="route('admin.issues.show', ['issue' => $issue->id, 'back' => url()->full()])"
                    :edit-url="route('admin.issues.edit', ['issue' => $issue->id, 'back' => url()->full()])"
                    :delete-target="'#confirmDeleteModal-' . $issue->id" />
                <x-admin.delete-modal type="issue" :object="$issue" />
            </div>
        @empty
            <div class="veh-issue-item done"><span class="desc">{{ __('Nessun guasto registrato') }}</span></div>
        @endforelse
    </div>
</div>
