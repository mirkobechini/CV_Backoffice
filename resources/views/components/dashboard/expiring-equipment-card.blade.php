@props(['expiringEquipment'])

<div class="dash-card">
    <div class="head">
        <h3><span class="ic" style="background:var(--blue-soft);color:var(--blue)"><i
                    class="fa-solid fa-toolbox"></i></span>{{ __('Attrezzature in scadenza') }}</h3>
    </div>
    <div class="body">
        @forelse ($expiringEquipment as $equipment)
            <a href="{{ route('admin.equipments.index') }}" class="dash-list-item">
                <div>
                    <div class="name">{{ $equipment->name }} <span class="meta">({{ $equipment->next_due_label }})</span></div>
                    <div class="meta">{{ $equipment->vehicle?->internal_code ?? '—' }} ·
                        @if ($equipment->next_due_date?->isPast())
                            {{ __('scaduta') }}
                        @else
                            {{ __('scade tra :days giorni', ['days' => \Carbon\Carbon::today()->diffInDays($equipment->next_due_date)]) }}
                        @endif
                    </div>
                </div>
                <span
                    class="badge {{ $equipment->next_due_date?->isPast() ? 'b-red' : 'b-amber' }}">{{ $equipment->next_due_date?->isPast() ? __('Scaduta') : \Carbon\Carbon::today()->diffInDays($equipment->next_due_date) . ' ' . __('gg') }}</span>
            </a>
        @empty
            <div class="dash-empty">
                <div>
                    <div class="name">{{ __('Nessuna attrezzatura in scadenza') }}</div>
                    <div class="meta">{{ __('Tutte le attrezzature sono in regola') }}</div>
                </div>
            </div>
        @endforelse
        @if ($expiringEquipment->isNotEmpty())
            <a href="{{ route('admin.equipments.index') }}"
                class="dash-see-all">{{ __('Vedi tutte le attrezzature') }} ›</a>
        @endif
    </div>
</div>
