// Entry Vite separato (non importato in app.js): FullCalendar serve solo
// qui, non ha senso nel bundle condiviso da ogni pagina (vedi ADR §18 —
// prima caricato da CDN pinnato a v6.1.15, indipendente da package.json).
import {Calendar} from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import itLocale from '@fullcalendar/core/locales/it';

document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const containerEl = document.getElementById('calendar-container');

    const calendar = new Calendar(calendarEl, {
        plugins: [dayGridPlugin, interactionPlugin],
        locale: itLocale,
        initialView: 'dayGridMonth',
        firstDay: 1,
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek',
        },
        buttonText: {
            today: 'Oggi',
            month: 'Mese',
            week: 'Settimana',
        },
        events: calendarEl.dataset.eventsUrl,
        eventClick: (info) => {
            info.jsEvent.preventDefault();
            if (info.event.url) {
                window.location.href = info.event.url;
            }
        },
        loading: (isLoading) => {
            containerEl.style.opacity = isLoading ? '0.5' : '1';
        },
    });

    calendar.render();
});
