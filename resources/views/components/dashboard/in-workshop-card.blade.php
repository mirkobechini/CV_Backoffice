@props(['inWorkshopRecords'])

@if ($inWorkshopRecords->isNotEmpty())
    <div class="dash-card">
        <div class="head">
            <h3><span class="ic" style="background:var(--red-soft);color:var(--red)"><i
                        class="fa-solid fa-wrench"></i></span>{{ __('Veicoli in officina') }}</h3>
        </div>
        <div class="body">
            @foreach ($inWorkshopRecords as $record)
                @php
                    $daysIn = max(1, (int) $record->appointment_date->diffInDays(\Illuminate\Support\Carbon::today()));
                @endphp
                <a href="{{ route('admin.maintenance-records.show', $record->id) }}" class="dash-list-item">
                    <div>
                        <div class="name">{{ $record->vehicle->internal_code }}</div>
                        <div class="meta">{{ $record->vehicle->license_plate }} ·
                            {{ $record->provider->name ?? __('Fornitore non indicato') }}</div>
                    </div>
                    <span class="badge b-amber">
                        {{ trans_choice('da :count giorno|da :count giorni', $daysIn, ['count' => $daysIn]) }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>
@endif
