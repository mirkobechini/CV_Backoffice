@props(['vehicle', 'activeDeadlines', 'assicurazione'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Scadenze') }}</h3>
        <a href="{{ route('admin.deadlines.create', ['vehicle_id' => $vehicle->id, 'back' => url()->full()]) }}"
            class="veh-btn-add" title="{{ __('Nuova scadenza') }}"><i class="fa-solid fa-plus"></i></a>
    </div>
    <div class="body">
        @forelse ($activeDeadlines as $deadline)
            <div class="veh-dl-item">
                <span class="dot-type leg-{{ $deadline->type_slug }}"></span>
                <div style="min-width:0;">
                    <div class="name">{{ $deadline->type }}</div>
                    <div class="date">
                        {{ __('scad.') }}
                        @if ($deadline->due_date)
                            {{ $deadline->due_date->format('d/m/Y') }}
                        @elseif ($deadline->km_remaining_label)
                            {{ $deadline->km_remaining_label }}
                        @else
                            —
                        @endif
                    </div>
                </div>
                <div class="veh-dl-badges">
                    <span
                        class="count c-{{ match ($deadline->status_color) {
                            'red' => 'red',
                            'yellow' => 'amber',
                            'green' => 'green',
                            default => 'gray',
                        } }}">{{ $deadline->days_label }}</span>
                    <a href="{{ route('admin.deadlines.show', $deadline->id) }}" class="mini-btn"
                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                    <a href="{{ route('admin.deadlines.edit', $deadline->id) }}" class="mini-btn"
                        title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                </div>
            </div>
        @empty
            <div class="veh-dl-item">
                <div><div class="name">{{ __('Nessuna scadenza attiva') }}</div></div>
            </div>
        @endforelse
        @if ($assicurazione)
            <div class="veh-dl-item">
                <div style="min-width:0;">
                    <div class="name">{{ __('Assicurazione') }}</div>
                    <div class="date">{{ __('scad.') }} {{ $assicurazione->due_date_formatted ?? '—' }}</div>
                </div>
                <div class="veh-dl-badges">
                    <a href="{{ route('admin.deadlines.show', $assicurazione->id) }}" class="mini-btn"
                        title="{{ __('Visualizza') }}"><i class="fa-solid fa-eye"></i></a>
                    <a href="{{ route('admin.deadlines.edit', $assicurazione->id) }}" class="mini-btn"
                        title="{{ __('Modifica') }}"><i class="fa-solid fa-pen"></i></a>
                </div>
            </div>
        @endif
    </div>
</div>
