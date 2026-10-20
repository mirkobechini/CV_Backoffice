@props(['totalVehicles', 'incompleteVehicles', 'expiringEquipment'])

{{-- Equipaggiamento (extra, non nel prototipo) + Attrezzature in scadenza --}}
<div class="dash-grid2">
    <div class="dash-card">
        <div class="head">
            <h3><span class="ic" style="background:var(--purple-soft);color:var(--purple)"><i
                        class="fa-solid fa-boxes-stacked"></i></span>{{ __('Equipaggiamento') }}</h3>
        </div>
        <div class="body">
            <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:6px;">
                <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                    <span class="name" style="font-size:17px;">{{ $totalVehicles - $incompleteVehicles->count() }}</span>
                    <span class="meta">/ {{ $totalVehicles }} {{ __('mezzi completi') }}</span>
                    @if ($incompleteVehicles->isNotEmpty())
                        <span class="badge b-amber" style="margin-left:auto;">
                            {{ trans_choice('{1} :count mezzo da integrare|[2,*] :count mezzi da integrare', $incompleteVehicles->count(), ['count' => $incompleteVehicles->count()]) }}
                        </span>
                    @endif
                </div>
                <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                    <div style="height:100%;background:var(--green);width:{{ $totalVehicles > 0 ? (($totalVehicles - $incompleteVehicles->count()) / $totalVehicles) * 100 : 0 }}%;">
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.vehicles.index', ['filter' => 'incomplete']) }}"
                class="dash-see-all">{{ __('Vedi veicoli da integrare') }} ›</a>
        </div>
    </div>
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
</div>
