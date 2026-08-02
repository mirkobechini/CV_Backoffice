@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="d-flex align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-clock-history me-2"></i>Registro Attività</h1>
            <div class="ms-auto">
                <form action="{{ route('admin.activity-log.index') }}" method="GET" class="d-flex gap-2" id="search-form">
                    <select name="log_name" class="form-select form-select-sm" style="min-width: 180px;">
                        <option value="">Tutti i tipi</option>
                        @foreach ($logNames as $name)
                            <option value="{{ $name }}" {{ request('log_name') === $name ? 'selected' : '' }}>
                                {{ ucfirst($name) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="input-group input-group-sm">
                        <input type="text" name="q" class="form-control" placeholder="Cerca..."
                            value="{{ request('q') }}" style="min-width: 200px;">
                        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        @if (request('q') || request('log_name'))
                            <a href="{{ route('admin.activity-log.index') }}" class="btn btn-outline-danger"><i
                                    class="bi bi-x-lg"></i></a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card my-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover my-0 align-middle">
                    <thead>
                        <tr>
                            <th>Data/Ora</th>
                            <th>Utente</th>
                            <th>Tipo</th>
                            <th>Descrizione</th>
                            <th>Dettagli</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activities as $activity)
                            <tr>
                                <td class="text-nowrap small">
                                    {{ $activity->created_at->locale('it')->translatedFormat('d/m/Y H:i') }}</td>
                                <td>{{ $activity->causer?->name ?? 'Sistema' }}</td>
                                <td><span class="badge bg-secondary">{{ $activity->log_name ?? 'N/A' }}</span></td>
                                <td>{{ $activity->description }}</td>
                                <td>
                                    @if ($activity->properties && $activity->properties->isNotEmpty())
                                        <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal"
                                            data-bs-target="#detailModal-{{ $activity->id }}">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        {{-- Modal dettagli --}}
                                        <div class="modal fade" id="detailModal-{{ $activity->id }}" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Dettagli attività</h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <pre class="mb-0" style="font-size: 0.8rem; max-height: 400px; overflow-y: auto;">{{ json_encode($activity->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">Nessuna attività registrata.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($activities->hasPages())
                <div class="d-flex justify-content-center py-3">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
