@extends('layouts.app')

@section('breadcrumb')
    <x-admin.breadcrumb :items="[
        ['label' => __('Servizi')],
        ['label' => __('Appuntamenti'), 'url' => route('admin.maintenance-records.index')],
        ['label' => __('Calendario')],
    ]" />
@endsection

@section('content')

    <div class="page-header">
        <h1>{{ __('Calendario Appuntamenti') }}</h1>
        <a href="{{ route('admin.maintenance-records.index') }}" class="btn">
            <i class="fa-solid fa-list"></i> {{ __('Vista lista') }}
        </a>
    </div>

    <div class="cal-card" id="calendar-container">
        <div id="calendar"></div>
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
                    const container = document.getElementById('calendar-container');
                    if (isLoading) {
                        container.style.opacity = '0.5';
                    } else {
                        container.style.opacity = '1';
                    }
                }
            });

            calendar.render();
        });
    </script>
@endpush
