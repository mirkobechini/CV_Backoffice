@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Sistema')],
        ['label' => __('Registro Attività')],
    ]" />
@endsection

@php
    $badgeClass = fn($type) => match ($type) {
        \App\Models\Vehicle::class => 'b-blue',
        \App\Models\Issue::class => 'b-red',
        \App\Models\Deadline::class => 'b-amber',
        \App\Models\MaintenanceRecord::class => 'b-green',
        \App\Models\Equipment::class, \App\Models\EquipmentType::class => 'b-purple',
        \App\Models\VehicleType::class => 'b-blue',
        \App\Models\Provider::class => 'b-blue',
        default => 'b-gray',
    };
@endphp

@section('content')

    <div class="page-header">
        <h1>{{ __('Registro Attività') }}</h1>
        <div class="filters">
            <form action="{{ route('admin.activity-log.index') }}" method="GET" class="filters"
                style="align-items:center;">
                <select name="subject_type" class="select" style="width:auto;" onchange="this.form.submit()">
                    <option value="">{{ __('Tutti i tipi') }}</option>
                    @foreach ($subjectTypes as $type => $label)
                        <option value="{{ $type }}" {{ request('subject_type') === $type ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
                <div class="search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="{{ __('Cerca...') }}" value="{{ request('q') }}">
                    @if (request('q') || request('subject_type'))
                        <a href="{{ route('admin.activity-log.index') }}" class="clear-search"><i
                                class="fa-solid fa-xmark"></i></a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('Data/Ora') }}</th>
                        <th>{{ __('Utente') }}</th>
                        <th>{{ __('Tipo') }}</th>
                        <th>{{ __('Descrizione') }}</th>
                        <th>{{ __('Dettagli') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activities as $activity)
                        <tr>
                            <td class="code" style="white-space:nowrap;">
                                {{ $activity->created_at->locale('it')->translatedFormat('d/m/Y H:i') }}</td>
                            <td>{{ $activity->causer?->name ?? __('Sistema') }}</td>
                            <td>
                                <span
                                    class="badge {{ $badgeClass($activity->subject_type) }}">{{ \App\Http\Controllers\Admin\ActivityLogController::subjectTypeLabel($activity->subject_type) }}</span>
                            </td>
                            <td>{{ $activity->description }}</td>
                            <td>
                                @if ($activity->properties && $activity->properties->isNotEmpty())
                                    <button type="button" class="detail-btn" data-bs-toggle="modal"
                                        data-bs-target="#detailModal-{{ $activity->id }}" title="{{ __('Dettagli') }}">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>

                                    <div class="modal fade" id="detailModal-{{ $activity->id }}" tabindex="-1"
                                        aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">{{ __('Dettagli attività') }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                        aria-label="{{ __('Chiudi') }}"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <pre class="log-detail">{{ json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <span class="hint" style="margin:0;">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty">{{ __('Nessuna attività registrata.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($activities->hasPages())
            <div class="pagination">
                <span>{{ __('Pagina') }} {{ $activities->currentPage() }} / {{ $activities->lastPage() }} ·
                    {{ $activities->total() }}</span>
                <nav>
                    {{ $activities->links() }}
                </nav>
            </div>
        @endif
    </div>
@endsection
