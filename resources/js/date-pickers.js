// Sostituisce i nativi <input type="date"/month"> con Flatpickr: il formato
// del calendario nativo del browser segue la lingua del sistema operativo,
// non l'attributo `lang` della pagina, quindi su molte macchine mostra le
// date in inglese anche con l'app in italiano. Flatpickr disegna un
// calendario proprio (tema definito in app.css) sempre in italiano.
import flatpickr from 'flatpickr';
import { Italian } from 'flatpickr/dist/l10n/it.js';
import monthSelectPlugin from 'flatpickr/dist/plugins/monthSelect/index.js';

const initializeDatePickers = () => {
    // Sposta id/aria dal campo reale (nascosto) all'altInput visibile, così
    // la <label for="..."> continua a puntare al campo che l'utente vede e
    // può effettivamente aprire il calendario cliccandoci sopra.
    const relabelToAltInput = (fp, originalId) => {
        if (!fp.altInput || !originalId) {
            return;
        }
        fp.altInput.id = originalId;
        fp.input.removeAttribute('id');
    };

    document.querySelectorAll('.flatpickr-date:not([data-flatpickr-ready])').forEach((el) => {
        el.dataset.flatpickrReady = '1';
        const originalId = el.id;
        const fp = flatpickr(el, {
            locale: Italian,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd/m/Y',
            altInputClass: el.dataset.altClass || 'input',
            allowInput: true,
        });
        relabelToAltInput(fp, originalId);
    });

    document.querySelectorAll('.flatpickr-month-input:not([data-flatpickr-ready])').forEach((el) => {
        el.dataset.flatpickrReady = '1';
        const originalId = el.id;
        const fp = flatpickr(el, {
            locale: Italian,
            plugins: [
                new monthSelectPlugin({
                    shorthand: false,
                    dateFormat: 'Y-m',
                    altFormat: 'F Y',
                }),
            ],
            altInput: true,
            altInputClass: el.dataset.altClass || 'input',
            allowInput: true,
        });
        relabelToAltInput(fp, originalId);
    });
};

document.addEventListener('DOMContentLoaded', initializeDatePickers);
