@props(['vehicle'])

<div class="veh-card">
    <div class="head">
        <h3>{{ __('Documenti') }}</h3>
    </div>
    <div class="body">
        <div class="veh-kv"><span class="k">{{ __('Immatricolazione') }}</span><span
                class="v">{{ $vehicle->immatricolation_date_formatted ?? 'N/A' }}</span></div>
        <div class="veh-kv"><span class="k">{{ __('Carta circolazione') }}</span><span class="v">
                @if ($vehicle->registration_card_path)
                    <a href="{{ Storage::disk(config('filesystems.uploads_disk'))->url($vehicle->registration_card_path) }}"
                        target="_blank" rel="noopener noreferrer">{{ __('Apri file') }}</a>
                @else
                    N/A
                @endif
            </span></div>
        <div class="veh-kv"><span class="k">{{ __('Garanzia') }}</span><span class="v">
                <span class="{{ $vehicle->is_warranty_expired ? 'no' : 'ok' }}">
                    {{ $vehicle->is_warranty_expired ? '✕' : '✓' }}
                    {{ $vehicle->warranty_expiration_date_formatted ?? 'N/A' }}
                </span>
            </span></div>
        @if ($vehicle->has_warranty_extension)
            <div class="veh-kv"><span class="k">{{ __('Estensione garanzia') }}</span><span
                    class="v">+{{ $vehicle->warranty_extension_duration }} {{ __('mesi') }}</span></div>
        @endif
    </div>
</div>
