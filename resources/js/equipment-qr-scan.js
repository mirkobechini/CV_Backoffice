// Entry Vite separato (non importato in app.js): html5-qrcode pesa troppo
// per essere nel bundle condiviso da ogni pagina, dato che serve solo qui
// (vedi ADR §17). Decodifica il QR già stampato sull'attrezzatura (che apre
// la sua scheda) ed estrae l'id per assegnarla al veicolo corrente.
import {Html5Qrcode} from 'html5-qrcode';

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('equipment-qr-reader');
    if (!container) return;

    const form = document.getElementById('equipment-scan-assign-form');
    const equipmentIdInput = document.getElementById('equipment-scan-id');
    const statusEl = document.getElementById('equipment-scan-status');
    const idPattern = /\/equipments\/(\d+)/;

    const scanner = new Html5Qrcode('equipment-qr-reader');
    let isProcessing = false;

    const onScanSuccess = (decodedText) => {
        if (isProcessing) return;

        const match = decodedText.match(idPattern);
        if (!match) {
            statusEl.textContent = 'QR non riconosciuto: non è l\'etichetta di un\'attrezzatura.';
            return;
        }

        isProcessing = true;
        equipmentIdInput.value = match[1];
        statusEl.textContent = 'Attrezzatura riconosciuta, assegnazione in corso...';
        scanner.stop().finally(() => form.submit());
    };

    scanner.start(
        {facingMode: 'environment'},
        {fps: 10, qrbox: {width: 250, height: 250}},
        onScanSuccess,
        () => {} // errore di frame singolo (nessun QR inquadrato): ignorato, non è un errore da mostrare
    ).catch(() => {
        statusEl.textContent = 'Impossibile accedere alla fotocamera. Verifica i permessi del browser.';
    });
});
