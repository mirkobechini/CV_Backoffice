@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Statistiche')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Statistiche guasti') }}</h1>
        <div class="filters">
            <a href="{{ route('admin.statistics.index', ['period' => 'all']) }}"
                class="chip {{ $period === 'all' ? 'on' : '' }}">{{ __('Tutto') }}</a>
            <a href="{{ route('admin.statistics.index', ['period' => '12m']) }}"
                class="chip {{ $period === '12m' ? 'on' : '' }}">{{ __('Ultimo anno') }}</a>
            <a href="{{ route('admin.statistics.index', ['period' => '6m']) }}"
                class="chip {{ $period === '6m' ? 'on' : '' }}">{{ __('Ultimi 6 mesi') }}</a>
        </div>
    </div>

    @php($maxPartCount = $partCounts->max() ?: 1)

    <div class="table-card" style="margin-bottom:16px;">
        <div class="toolbar">
            <h2>{{ __('Guasti per parte del veicolo') }}</h2>
        </div>
        <div class="body" style="padding:16px 20px;">
            @forelse ($partCounts as $part => $count)
                <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                    <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                        <span class="name">{{ $part }}</span>
                        <span class="meta" style="margin-left:auto;">{{ $count }}</span>
                    </div>
                    <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                        <div style="height:100%;background:var(--blue);width:{{ ($count / $maxPartCount) * 100 }}%;">
                        </div>
                    </div>
                </div>
            @empty
                <p class="hint" style="margin:0;">{{ __('Nessun guasto con parte assegnata nel periodo selezionato.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="table-card" style="margin-bottom:16px;">
        <div class="toolbar">
            <h2>{{ __('Guasti per parte e veicolo') }}</h2>
        </div>
        @if (empty($vehiclePartMatrix['vehicles']))
            <div class="body" style="padding:16px 20px;">
                <p class="hint" style="margin:0;">{{ __('Nessun dato per il periodo selezionato.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Veicolo') }}</th>
                            @foreach ($vehiclePartMatrix['parts'] as $part)
                                <th>{{ $part }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vehiclePartMatrix['vehicles'] as $vehicleLabel)
                            <tr>
                                <td class="code">{{ $vehicleLabel }}</td>
                                @foreach ($vehiclePartMatrix['parts'] as $part)
                                    <td>{{ $vehiclePartMatrix['matrix'][$vehicleLabel][$part] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @php($maxMonthlyCount = $monthlyTrend->max() ?: 1)

    <div class="table-card" style="margin-bottom:16px;">
        <div class="toolbar">
            <h2>{{ __('Andamento mensile') }}</h2>
        </div>
        <div class="body" style="padding:16px 20px;">
            @foreach ($monthlyTrend as $month => $count)
                <div class="dash-list-item" style="align-items:flex-start;flex-direction:column;gap:4px;">
                    <div style="display:flex;align-items:baseline;gap:6px;width:100%;">
                        <span class="name">{{ ucfirst(\Carbon\Carbon::createFromFormat('Y-m', $month)->locale('it')->translatedFormat('F Y')) }}</span>
                        <span class="meta" style="margin-left:auto;">{{ $count }}</span>
                    </div>
                    <div style="width:100%;height:6px;border-radius:999px;background:var(--surface-3);overflow:hidden;">
                        <div style="height:100%;background:var(--amber);width:{{ ($count / $maxMonthlyCount) * 100 }}%;">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="table-card">
        <div class="toolbar">
            <h2>{{ __('Costo riparazioni per parte') }}</h2>
        </div>
        @if (empty($costByPart))
            <div class="body" style="padding:16px 20px;">
                <p class="hint" style="margin:0;">{{ __('Nessun costo registrato per il periodo selezionato.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('Parte') }}</th>
                            <th>{{ __('Costo totale') }}</th>
                            <th>{{ __('Guasti con costo registrato') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($costByPart as $part => $data)
                            <tr>
                                <td>{{ $part }}</td>
                                <td>€ {{ number_format($data['cost'], 2, ',', '.') }}</td>
                                <td>{{ $data['count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="hint" style="padding:8px 20px 16px;">
                {{ __("Quando un appuntamento copre più guasti insieme, il costo viene diviso in parti uguali tra loro.") }}
            </div>
        @endif
    </div>
@endsection
