@props(['deadlines'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Stato Scadenze') }}</h3>
    </div>
    <div class="body">
        @forelse ($deadlines as $deadline)
            <div class="veh-dl-item">
                <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                <div style="min-width:0;">
                    <div class="name">{{ $deadline->type }}</div>
                    <div class="date">{{ __('scad.') }} {{ $deadline->due_date?->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div class="veh-dl-badges">
                    <span
                        class="count c-{{ match ($deadline->status_color) {
                            'red' => 'red',
                            'yellow' => 'amber',
                            'green' => 'green',
                            default => 'gray',
                        } }}">{{ $deadline->status_label }}</span>
                    <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="mini-btn"
                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                </div>
            </div>
        @empty
            <div class="veh-dl-item"><div><div class="name">{{ __('Nessuna scadenza registrata') }}</div></div>
            </div>
        @endforelse
    </div>
</div>
