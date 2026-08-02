@extends('layouts.app')
@section('content')
    <div class="container py-4">
        <div class="d-flex align-items-center mb-4">
            <h1 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Calendario Appuntamenti</h1>
            <div class="ms-auto">
                <a href="{{ route('admin.maintenance-records.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-list me-1"></i>Vista lista
                </a>
            </div>
        </div>
        <div class="card">
            <div class="card-body" id="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/index.global.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.15/index.global.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.15/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/interaction@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            if (!calendarEl) return;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'it',
                firstDay: 1,
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,dayGridWeek'
                },
                buttonText: {
                    today: 'Oggi',
                    month: 'Mese',
                    week: 'Settimana'
                },
                events: '{{ route('admin.maintenance-records.events') }}',
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    if (info.event.url) {
                        window.location.href = info.event.url;
                    }
                },
                loading: function(isLoading) {
                    if (isLoading) {
                        document.getElementById('calendar-container').classList.add('opacity-50');
                    } else {
                        document.getElementById('calendar-container').classList.remove('opacity-50');
                    }
                }
            });

            calendar.render();
        });
    </script>
@endpush
