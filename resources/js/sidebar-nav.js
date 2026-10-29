// Sottomenu a tendina (Carburante/Pedaggi/Tessere, Attrezzature) e ricerca
// rapida veicoli in sidebar — restyle "Saru", ADR §19.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-sidebar-submenu-toggle]').forEach((toggle) => {
        const submenu = document.getElementById(toggle.dataset.sidebarSubmenuToggle);
        if (!submenu) return;

        toggle.addEventListener('click', () => {
            const isOpen = submenu.classList.toggle('open');
            toggle.classList.toggle('open', isOpen);
        });
    });

    const input = document.getElementById('sidebar-vehicle-search');
    const results = document.getElementById('sidebar-vehicle-search-results');
    if (!input || !results) return;

    const suggestionsUrl = input.dataset.suggestionsUrl;
    const vehicleUrlBase = input.dataset.vehicleUrlBase;
    let debounceTimer = null;
    let currentVehicles = [];
    let highlightedIndex = -1;

    const closeResults = () => {
        results.classList.remove('is-open');
        results.innerHTML = '';
        currentVehicles = [];
        highlightedIndex = -1;
    };

    // DOM diretto (no innerHTML) per sigla/targa: sono campi testo inseriti
    // dagli utenti, stesso motivo per cui l'autocomplete guasti non usa
    // innerHTML (audit sicurezza 2026-10-22, SH5).
    const renderResults = (vehicles) => {
        currentVehicles = vehicles;
        highlightedIndex = -1;
        results.replaceChildren();

        if (vehicles.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'sidebar-search-empty';
            empty.textContent = input.dataset.emptyLabel || 'Nessun veicolo trovato';
            results.appendChild(empty);
            results.classList.add('is-open');
            return;
        }

        vehicles.forEach((v) => {
            const link = document.createElement('a');
            link.className = 'sidebar-search-result';
            link.href = `${vehicleUrlBase}/${encodeURIComponent(v.id)}`;

            const code = document.createElement('span');
            code.className = 'code';
            code.textContent = v.internal_code ?? '';

            const plate = document.createElement('span');
            plate.className = 'plate';
            plate.textContent = v.license_plate ?? '';

            link.append(code, plate);
            results.appendChild(link);
        });

        results.classList.add('is-open');
    };

    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(debounceTimer);

        if (q.length === 0) {
            closeResults();
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch(`${suggestionsUrl}?q=${encodeURIComponent(q)}`)
                .then((r) => (r.ok ? r.json() : []))
                .then(renderResults)
                .catch(() => closeResults());
        }, 250);
    });

    input.addEventListener('keydown', (event) => {
        const items = results.querySelectorAll('.sidebar-search-result');
        if (items.length === 0) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlightedIndex = Math.max(highlightedIndex - 1, 0);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            const target = items[highlightedIndex >= 0 ? highlightedIndex : 0];
            if (target) window.location.href = target.href;
            return;
        } else if (event.key === 'Escape') {
            closeResults();
            return;
        } else {
            return;
        }

        items.forEach((item, i) => item.classList.toggle('is-highlighted', i === highlightedIndex));
    });

    document.addEventListener('click', (event) => {
        if (!input.contains(event.target) && !results.contains(event.target)) {
            closeResults();
        }
    });
});
