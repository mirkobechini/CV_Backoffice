@props(['totalVehicles', 'incompleteVehicles'])

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
