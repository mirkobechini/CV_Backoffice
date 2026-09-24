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
    const index = root.querySelector('.tire-size-index')?.value.trim();

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
    });
};

document.addEventListener('DOMContentLoaded', initializeTireSizeInputs);
