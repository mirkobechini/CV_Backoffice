// Compone/scompone una misura pneumatico (es. "225/75R16C") tra il campo
// nascosto inviato al server e i sotto-campi visibili (larghezza/profilo/
// cerchio/rinforzato/indice), così il formato canonico è garantito dalla
// struttura dell'input invece che da una validazione a posteriori su testo
// libero. Stesso schema di inizializzazione di date-pickers.js.
const TIRE_SIZE_PATTERN = /^(\d{2,3})\/(\d{2,3})R(\d{2})(C)?(?:\s(.+))?$/i;

const composeTireSize = (root) => {
    const hidden = document.getElementById(root.dataset.tireSizeFor);
    if (!hidden) return;

    const width = root.querySelector('.tire-size-width')?.value.trim();
    const ratio = root.querySelector('.tire-size-ratio')?.value.trim();
    const rim = root.querySelector('.tire-size-rim')?.value.trim();
    const reinforced = root.querySelector('.tire-size-reinforced')?.checked;
    const index = root.querySelector('.tire-size-index')?.value.trim().toUpperCase();

    if (!width || !ratio || !rim) {
        hidden.value = '';
        return;
    }

    let composed = `${width}/${ratio}R${rim}`;
    if (reinforced) {
        composed += 'C';
    }
    if (index) {
        composed += ` ${index}`;
    }

    hidden.value = composed;
};

const parseTireSize = (root, value) => {
    const match = value.match(TIRE_SIZE_PATTERN);
    if (!match) return;

    const [, width, ratio, rim, reinforced, index] = match;

    const widthEl = root.querySelector('.tire-size-width');
    const ratioEl = root.querySelector('.tire-size-ratio');
    const rimEl = root.querySelector('.tire-size-rim');
    const reinforcedEl = root.querySelector('.tire-size-reinforced');
    const indexEl = root.querySelector('.tire-size-index');

    if (widthEl) widthEl.value = width;
    if (ratioEl) ratioEl.value = ratio;
    if (rimEl) rimEl.value = rim;
    if (reinforcedEl) reinforcedEl.checked = Boolean(reinforced);
    if (indexEl && index) indexEl.value = index;
};

// Popola il select "misura suggerita dal veicolo" con le misure consigliate
// (allowed_tire_sizes) del veicolo attualmente selezionato nel form, lette
// dagli attributi data-tire-sizes delle <option> del select veicolo (vedi
// vehicle-select-tire-sizes.js). Nascosto se il veicolo non ne ha nessuna.
const refreshTireSizeSuggestions = (root) => {
    const vehicleSelectId = root.dataset.tireSizeVehicleSelect;
    if (!vehicleSelectId) return;

    const suggestSelect = root.querySelector('.tire-size-suggest');
    const vehicleSelect = document.getElementById(vehicleSelectId);
    if (!suggestSelect || !vehicleSelect) return;

    const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
    let sizes = [];
    try {
        sizes = JSON.parse(selectedOption?.dataset.tireSizes || '[]');
    } catch {
        sizes = [];
    }

    suggestSelect.innerHTML = `<option value="">${suggestSelect.dataset.placeholder || ''}</option>`;
    sizes.forEach((size) => {
        const option = document.createElement('option');
        option.value = size;
        option.textContent = size;
        suggestSelect.appendChild(option);
    });
    suggestSelect.style.display = sizes.length ? '' : 'none';
};

const initializeTireSizeInputs = () => {
    document.querySelectorAll('.tire-size-input:not([data-tire-size-ready])').forEach((root) => {
        root.dataset.tireSizeReady = '1';

        const hidden = document.getElementById(root.dataset.tireSizeFor);
        if (hidden?.value) {
            parseTireSize(root, hidden.value);
        }

        root.querySelectorAll('.tire-size-fields input').forEach((input) => {
            input.addEventListener('input', () => composeTireSize(root));
            input.addEventListener('change', () => composeTireSize(root));
        });

        const vehicleSelectId = root.dataset.tireSizeVehicleSelect;
        if (vehicleSelectId) {
            const suggestSelect = root.querySelector('.tire-size-suggest');
            suggestSelect?.addEventListener('change', () => {
                if (!suggestSelect.value) return;
                parseTireSize(root, suggestSelect.value);
                composeTireSize(root);
            });

            document.getElementById(vehicleSelectId)?.addEventListener('change', () => refreshTireSizeSuggestions(root));
            refreshTireSizeSuggestions(root);
        }
    });
};

// Esposta per poter re-inizializzare solo i nuovi campi dopo averne clonato
// uno (vedi addTireSizeRow sotto), senza ripetere qui la logica di ricerca.
window.initTireSizeInputs = initializeTireSizeInputs;

let tireSizeRowCounter = 0;

// Aggiunge una riga "misura pneumatico" clonando il <template> del gruppo
// (usato per l'elenco ripetibile "misure consigliate" del veicolo, dove un
// veicolo può avere più di una misura, es. assale anteriore/posteriore
// diversi). Gli id vengono resi unici per ogni riga clonata sostituendo il
// suffisso "_new" usato nel template.
const addTireSizeRow = (group) => {
    const container = group.querySelector('[data-tire-size-container]');
    const template = group.querySelector('[data-tire-size-template]');
    if (!container || !template) return;

    const clone = template.content.cloneNode(true);
    const root = clone.querySelector('.tire-size-input');
    if (!root) return;

    const uniqueSuffix = `_added_${tireSizeRowCounter++}`;
    const oldFor = root.dataset.tireSizeFor;
    const newFor = oldFor.replace('_new', uniqueSuffix);
    root.dataset.tireSizeFor = newFor;

    clone.querySelectorAll('[id]').forEach((el) => {
        el.id = el.id.replace('_new', uniqueSuffix);
    });
    clone.querySelectorAll('label[for]').forEach((el) => {
        el.setAttribute('for', el.getAttribute('for').replace('_new', uniqueSuffix));
    });

    container.appendChild(clone);
    initializeTireSizeInputs();
};

document.addEventListener('DOMContentLoaded', () => {
    initializeTireSizeInputs();

    document.querySelectorAll('[data-tire-size-group]').forEach((group) => {
        group.querySelector('[data-tire-size-add]')?.addEventListener('click', () => addTireSizeRow(group));
    });

    // Delegato: le righe rimovibili possono essere aggiunte dinamicamente.
    document.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('.tire-size-remove');
        if (removeBtn) {
            removeBtn.closest('.tire-size-input')?.remove();
        }
    });
});
