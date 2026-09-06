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
    <style>
        /* Adatta FullCalendar al tema scuro usando le variabili Bootstrap */
        [data-bs-theme="dark"] .fc {
            --fc-border-color: var(--bs-border-color);
            --fc-page-bg-color: var(--bs-body-bg);
            --fc-neutral-bg-color: var(--bs-tertiary-bg);
            --fc-list-event-hover-bg-color: var(--bs-tertiary-bg);
            --fc-today-bg-color: rgba(13, 110, 253, 0.15);
            color: var(--bs-body-color);
        }

        [data-bs-theme="dark"] .fc .fc-toolbar-title {
            color: var(--bs-body-color);
        }

        [data-bs-theme="dark"] .fc .fc-button {
            background-color: var(--bs-secondary-bg);
            border-color: var(--bs-border-color);
            color: var(--bs-body-color);
        }

        [data-bs-theme="dark"] .fc .fc-button:hover {
            background-color: var(--bs-tertiary-bg);
        }

        [data-bs-theme="dark"] .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }

        [data-bs-theme="dark"] .fc .fc-daygrid-day-number,
        [data-bs-theme="dark"] .fc .fc-col-header-cell-cushion {
            color: var(--bs-body-color);
        }

        [data-bs-theme="dark"] .fc .fc-daygrid-day.fc-day-today {
            background-color: rgba(13, 110, 253, 0.15);
        }

        [data-bs-theme="dark"] .fc .fc-daygrid-day.fc-day-other {
            opacity: 0.4;
        }
    </style>
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
