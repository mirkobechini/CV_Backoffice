// Converte in JPEG, direttamente nel browser, le foto HEIC/HEIF caricate
// (il formato di default delle foto scattate su iPhone): il server ora le
// accetta (vedi StoreIssueRequest/UpdateIssueRequest), ma nessun browser
// oltre Safari sa visualizzarle in un tag <img>. Convertendo qui prima
// dell'invio, sul server arriva già un JPEG mostrabile ovunque — senza
// bisogno di librerie di decodifica HEIC lato server (libheif), che non è
// detto siano disponibili sull'hosting.
//
// heic2any include l'intero decoder WASM di libheif (~1.3MB): un import
// statico lo metterebbe nel bundle principale, scaricato ad ogni pagina
// da chiunque, anche chi non tocca mai un campo immagine. import()
// dinamico lo scarica solo quando serve davvero, cioè quando qualcuno
// seleziona effettivamente un file HEIC/HEIF.
const isHeicFile = (file) => {
    const name = (file.name || '').toLowerCase();
    return (
        file.type === 'image/heic' ||
        file.type === 'image/heif' ||
        name.endsWith('.heic') ||
        name.endsWith('.heif')
    );
};

const convertToJpeg = async (file) => {
    const { default: heic2any } = await import('heic2any');
    const result = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 });
    const converted = Array.isArray(result) ? result[0] : result;
    const newName = file.name.replace(/\.(heic|heif)$/i, '.jpg');

    return new File([converted], newName, { type: 'image/jpeg' });
};

const initializeHeicConversion = () => {
    document.querySelectorAll('input[type="file"].heic-convert:not([data-heic-ready])').forEach((input) => {
        input.dataset.heicReady = '1';

        const form = input.closest('form');
        const submitBtn = form ? form.querySelector('[type="submit"]') : null;
        const label = input.id ? document.getElementById(`${input.id}_label`) : null;
        const originalLabelHtml = label ? label.innerHTML : null;

        input.addEventListener('change', async () => {
            const file = input.files && input.files[0];

            if (!file || !isHeicFile(file)) {
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
            }
            if (label) {
                label.textContent = 'Conversione immagine…';
            }

            try {
                const converted = await convertToJpeg(file);
                const transfer = new DataTransfer();
                transfer.items.add(converted);
                input.files = transfer.files;
                // Ritrigghera il listener della pagina (mostra il nome del
                // file convertito nell'etichetta), senza rientrare in
                // questo stesso handler: isHeicFile() sul nuovo file .jpg
                // torna false.
                input.dispatchEvent(new Event('change', { bubbles: false }));
            } catch (error) {
                console.error('Conversione HEIC fallita, verrà caricato il file originale.', error);
                if (label && originalLabelHtml !== null) {
                    label.textContent = file.name;
                }
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            }
        });
    });
};

document.addEventListener('DOMContentLoaded', initializeHeicConversion);
