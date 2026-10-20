{{-- Script condiviso tra create.blade.php ed edit.blade.php: stessi id di
campo in entrambi i form, prima duplicato verbatim nei due file (audit
2026-10-19, C12e). --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const licensePlateInput = document.getElementById('license_plate');
        const warrantyExtensionCheckbox = document.getElementById('has_warranty_extension');
        const warrantyExpirationDateInput = document.getElementById('warranty_expiration_date');
        const warrantyExtensionDurationInput = document.getElementById('warranty_extension_duration');
        const registrationCardInput = document.getElementById('registration_card');
        const registrationCardLabel = document.getElementById('registration_card_label');
        const registrationCardDefaultText = registrationCardLabel.innerHTML;
        const scanBtn = document.getElementById('scan-libretto-btn');
        const scanForm = document.getElementById('scan-libretto-form');
        const scanPhotoFrontInput = document.getElementById('scan_photo_front_hidden');

        // Mantiene lato client il formato targa coerente con le regole server.
        function uppercaseLicensePlate() {
            licensePlateInput.value = licensePlateInput.value.toUpperCase().replace(/\s+/g, '');
        }

        // I campi garanzia diventano obbligatori solo con estensione attiva.
        function toggleWarrantyRequiredFields() {
            const isChecked = warrantyExtensionCheckbox.checked;

            warrantyExpirationDateInput.required = isChecked;
            warrantyExtensionDurationInput.required = isChecked;
        }

        // Mostra il nome del file selezionato nella zona di upload.
        function updateRegistrationCardLabel() {
            if (registrationCardInput.files.length > 0) {
                registrationCardLabel.textContent = registrationCardInput.files[0].name;
            } else {
                registrationCardLabel.innerHTML = registrationCardDefaultText;
            }
        }

        // Il pulsante resta disabilitato finché il libretto non è stato caricato.
        function updateScanButtonState() {
            scanBtn.disabled = !registrationCardInput.files.length;
        }

        // Invia allo scan lo stesso file scelto per il libretto, senza
        // selettore duplicato: lo copia nel form nascosto tramite
        // DataTransfer e lo invia.
        scanBtn.addEventListener('click', () => {
            if (scanBtn.disabled) {
                return;
            }

            const frontTransfer = new DataTransfer();
            frontTransfer.items.add(registrationCardInput.files[0]);
            scanPhotoFrontInput.files = frontTransfer.files;

            scanBtn.disabled = true;
            scanBtn.innerHTML = scanBtn.dataset.loadingText;
            scanForm.submit();
        });

        toggleWarrantyRequiredFields();
        uppercaseLicensePlate();
        warrantyExtensionCheckbox.addEventListener('change', toggleWarrantyRequiredFields);
        licensePlateInput.addEventListener('input', uppercaseLicensePlate);
        registrationCardInput.addEventListener('change', () => {
            updateRegistrationCardLabel();
            updateScanButtonState();
        });
    });
</script>
