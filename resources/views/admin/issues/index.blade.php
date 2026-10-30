@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Manutenzione')],
        ['label' => __('Guasti')],
    ]" />
@endsection

@section('content')
    @php
        $statusFilterUrl = fn($status) => route('admin.issues.index', array_merge(request()->except(['status_filter', 'page']), $status === 'all' ? [] : ['status_filter' => $status]));
    @endphp

    <div class="table-card">
        <div class="toolbar toolbar-reorder-mobile">
            <div class="toolbar-left">
                <h2 class="toolbar-title-redundant">{{ __('Elenco guasti') }}</h2>
                <div class="filters">
                    <a href="{{ $statusFilterUrl('all') }}"
                        class="chip {{ $statusFilter === 'all' ? 'on' : '' }}">{{ __('Tutti') }}</a>
                    <a href="{{ $statusFilterUrl('open') }}"
                        class="chip {{ $statusFilter === 'open' ? 'on' : '' }}">{{ __('Aperti') }}</a>
                    <a href="{{ $statusFilterUrl('in_progress') }}"
                        class="chip {{ $statusFilter === 'in_progress' ? 'on' : '' }}">{{ __('In lavorazione') }}</a>
                    <a href="{{ $statusFilterUrl('closed') }}"
                        class="chip {{ $statusFilter === 'closed' ? 'on' : '' }}">{{ __('Risolti') }}</a>
                </div>
            </div>
            <div class="filters">
                <form action="{{ route('admin.issues.index') }}" method="GET" class="search">
                    @foreach (request()->except('q', 'page') as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('cerca veicolo o descrizione') }}"
                        value="{{ request('q') }}">
                    @if (request('q'))
                        <a href="{{ route('admin.issues.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </form>
                {{-- Scroll orizzontale invece di a capo sotto i 768px: tre
                pulsanti con etichetta lunga impilati verticalmente
                prendevano troppo spazio sopra la lista (restyle "Saru",
                issue-563). --}}
                <div class="toolbar-icon-actions">
                    @if ($issuesWithoutPartCount > 0 && auth()->user()->canUseLicenseFeature(\App\Models\License::FEATURE_AI_BACKFILL))
                        <a href="{{ route('admin.issues.backfill-parts.index') }}" class="btn" title="{{ __('Assegna la parte del veicolo ai guasti che ne sono ancora privi') }}">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('Allinea parti') }} ({{ $issuesWithoutPartCount }})
                        </a>
                    @endif
                    <a href="{{ route('admin.csv.export', 'issues') }}" class="btn" title="{{ __('Scarica CSV') }}">
                        <i class="fa-solid fa-download"></i> CSV
                    </a>
                    <a href="{{ route('admin.csv-import.index') }}" class="btn">
                        <i class="fa-solid fa-file-import"></i> {{ __('Importa CSV') }}
                    </a>
                </div>
                <a href="{{ route('admin.issues.create') }}" class="btn primary">
                    <i class="fa-solid fa-plus"></i> {{ __('Nuovo guasto') }}
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>
                            <div class="th-wrap"><span>{{ __('Veicolo') }}</span>
                                <a href="{{ $groupToggleUrl('vehicle') }}"
                                    class="mini {{ $groupBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per veicolo') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('vehicle') }}"
                                    class="mini {{ $sortBy === 'vehicle' ? 'on' : '' }}"
                                    title="{{ __('Ordina per veicolo') }}">{{ $sortIcon('vehicle') }}</a>
                            </div>
                        </th>
                        <th>{{ __('Descrizione') }}</th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Parte') }}</span>
                                <a href="{{ $groupToggleUrl('part') }}"
                                    class="mini {{ $groupBy === 'part' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per parte') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('part') }}"
                                    class="mini {{ $sortBy === 'part' ? 'on' : '' }}"
                                    title="{{ __('Ordina per parte') }}">{{ $sortIcon('part') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Stato') }}</span>
                                <a href="{{ $groupToggleUrl('status') }}"
                                    class="mini {{ $groupBy === 'status' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per stato') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('status') }}"
                                    class="mini {{ $sortBy === 'status' ? 'on' : '' }}"
                                    title="{{ __('Ordina per stato') }}">{{ $sortIcon('status') }}</a>
                            </div>
                        </th>
                        <th>
                            <div class="th-wrap"><span>{{ __('Data') }}</span>
                                <a href="{{ $groupToggleUrl('date') }}"
                                    class="mini {{ $groupBy === 'date' ? 'on' : '' }}"
                                    title="{{ __('Raggruppa per data') }}">Grp</a>
                                <a href="{{ $sortToggleUrl('date') }}"
                                    class="mini {{ $sortBy === 'date' ? 'on' : '' }}"
                                    title="{{ __('Ordina per data') }}">{{ $sortIcon('date') }}</a>
                            </div>
                        </th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $groups = $groupBy !== null ? $groupedIssues : collect([__('Tutti i guasti') => $issues]);
                    @endphp

                    @forelse ($groups as $groupLabel => $groupIssues)
                        @if ($groupBy !== null)
                            <tr class="group-row" data-group-row="g{{ $loop->index }}" tabindex="0" role="button" aria-expanded="true">
                                <td colspan="6"><i class="fa-solid fa-chevron-down group-chevron"></i>{{ $groupLabel }} ({{ $groupIssues->count() }})</td>
                            </tr>
                        @endif

                        @foreach ($groupIssues as $issue)
                            <tr @if ($groupBy !== null) data-groups="g{{ $loop->parent->index }}" @endif>
                                <td>
                                    <div class="vin">
                                        <span class="thumb t{{ ($issue->vehicle_id % 5) + 1 }}"><i
                                                class="fa-solid fa-truck"></i></span>
                                        <div>
                                            <div class="vin-name">{{ $issue->vehicle->brand->name ?? '' }}
                                                {{ $issue->vehicle->carModel->name ?? 'N/A' }}</div>
                                            <div class="vin-sub">{{ $issue->vehicle->internal_code ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $issue->description }}</td>
                                <td>{{ $issue->part ?? 'N/A' }}</td>
                                <td>
                                    <x-admin.status-badge :color="$issue->status_color">{{ $issue->status_label }}</x-admin.status-badge>
                                </td>
                                <td>{{ $issue->event_date_formatted ?? 'N/A' }}</td>
                                <td>
                                    <x-admin.row-actions
                                        :show-url="route('admin.issues.show', $issue->id)"
                                        :edit-url="route('admin.issues.edit', $issue->id)"
                                        :delete-target="'#confirmDeleteModal-' . $issue->id" />
                                </td>
                            </tr>
                            <x-admin.delete-modal type="issue" :object="$issue" />
                        @endforeach
                    @empty
                        <tr>
                            <td colspan="6" class="empty">{{ __('Nessun guasto trovato.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
