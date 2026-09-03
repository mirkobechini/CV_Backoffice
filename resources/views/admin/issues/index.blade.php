@extends('layouts.app')
@section('content')
    <x-admin.index-table title="Guasti" :searchRoute="route('admin.issues.index')" :csvRoute="route('admin.csv.export', 'issues')">
        <x-slot:headingActions>
            <x-admin.create-button :href="route('admin.issues.create')" label="guasto" />
            <a href="{{ route('admin.csv-import.index') }}"
                class="btn btn-outline-primary btn-sm d-none d-md-inline-block">Importa CSV</a>
        </x-slot:headingActions>

        <x-slot:head>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Veicolo</span>
                    <a href="{{ $groupToggleUrl('vehicle') }}"
                        class="btn btn-sm {{ $groupBy === 'vehicle' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per veicolo">Grp</a>
                    <a href="{{ $sortToggleUrl('vehicle') }}"
                        class="btn btn-sm {{ $sortBy === 'vehicle' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per veicolo">{{ $sortIcon('vehicle') }}</a>
                </div>
            </th>
            <th scope="col">Descrizione</th>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Stato</span>
                    <a href="{{ $groupToggleUrl('status') }}"
                        class="btn btn-sm {{ $groupBy === 'status' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per stato">Grp</a>
                    <a href="{{ $sortToggleUrl('status') }}"
                        class="btn btn-sm {{ $sortBy === 'status' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per stato">{{ $sortIcon('status') }}</a>
                </div>
            </th>
            <th scope="col">
                <div class="d-inline-flex align-items-center gap-1">
                    <span>Data</span>
                    <a href="{{ $groupToggleUrl('date') }}"
                        class="btn btn-sm {{ $groupBy === 'date' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Raggruppa per data">Grp</a>
                    <a href="{{ $sortToggleUrl('date') }}"
                        class="btn btn-sm {{ $sortBy === 'date' ? 'btn-primary' : 'btn-outline-secondary' }}"
                        title="Ordina per data">{{ $sortIcon('date') }}</a>
                </div>
            </th>
            <th scope="col">Azioni</th>
        </x-slot:head>

        <x-slot:rows>
            @php
                $groups = $groupBy !== null ? $groupedIssues : collect(['Tutti i guasti' => $issues]);
            @endphp

            @foreach ($groups as $groupLabel => $groupIssues)
                @php
                    $groupId = $groupBy !== null ? \Illuminate\Support\Str::slug($groupLabel) : 'all';
                @endphp
                @if ($groupBy !== null)
                    <tr class="table-light" role="button" data-bs-toggle="collapse"
                        data-bs-target=".collapse-{{ $groupId }}" aria-expanded="true" style="cursor: pointer;">
                        <td colspan="5">
                            <span class="collapse-indicator me-2">▼</span>
                            <strong>{{ $groupLabel }}</strong> ({{ $groupIssues->count() }})
                        </td>
                    </tr>
                @endif

                @foreach ($groupIssues as $issue)
                    <tr class="collapse-{{ $groupId }} collapse show">
                        <td>{{ $issue->vehicle->internal_code }}</td>
                        <td>{{ $issue->description }}</td>
                        <td>
                            <span
                                class="badge bg-{{ match ($issue->status_color) {'red' => 'danger','yellow' => 'warning text-dark','green' => 'success',default => 'secondary'} }}">{{ $issue->status_label }}</span>
                        </td>
                        <td>{{ $issue->event_date_formatted }}</td>
                        <x-admin.row-actions :showUrl="route('admin.issues.show', $issue->id)" :editUrl="route('admin.issues.edit', $issue->id)" :deleteTarget="'#confirmDeleteModal-' . $issue->id" :label="'guasto ' . $issue->description" />
                    </tr>
                    <x-admin.delete-modal type="issue" :object="$issue" />
                @endforeach
            @endforeach
        </x-slot:rows>
    </x-admin.index-table>
    @push('scripts')
        <script>
            document.addEventListener('click', function(e) {
                const toggle = e.target.closest('[data-bs-toggle="collapse"]');
                if (!toggle) return;
                const indicator = toggle.querySelector('.collapse-indicator');
                if (indicator) {
                    const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                    indicator.textContent = isExpanded ? '▶' : '▼';
                }
            });
        </script>
    @endpush
@endsection
