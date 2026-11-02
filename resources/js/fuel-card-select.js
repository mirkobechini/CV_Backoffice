// Nel form rifornimento, quando si seleziona un veicolo porta in cima alla
// select "Tessera usata" le tessere legate a quel veicolo.
const initializeFuelCardSelect = () => {
    const vehicleSelect = document.getElementById('vehicle_id');
    const fuelCardSelect = document.getElementById('fuel_card_id');

    if (!vehicleSelect || !fuelCardSelect) {
        return;
    }

    const reorderFuelCardsForSelectedVehicle = () => {
        const selectedVehicleId = vehicleSelect.value;

        if (!selectedVehicleId) {
            return;
        }

        const options = Array.from(fuelCardSelect.options);
        const placeholderOption = options.find((option) => option.value === '');
        const cardOptions = options.filter((option) => option.value !== '');

        cardOptions.sort((a, b) => {
            const aMatches = a.dataset.vehicleId === selectedVehicleId ? 0 : 1;
            const bMatches = b.dataset.vehicleId === selectedVehicleId ? 0 : 1;
            return aMatches - bMatches;
        });

        const previousValue = fuelCardSelect.value;

        if (placeholderOption) {
            fuelCardSelect.appendChild(placeholderOption);
        }
        cardOptions.forEach((option) => fuelCardSelect.appendChild(option));

        fuelCardSelect.value = previousValue;
    };

    vehicleSelect.addEventListener('change', reorderFuelCardsForSelectedVehicle);
    reorderFuelCardsForSelectedVehicle();
};

// Precompila il carburante con quello del veicolo selezionato, ma solo per
// i veicoli con un'alimentazione univoca (non "ibrido", dove l'utente deve
// scegliere consapevolmente il carburante di questo specifico rifornimento,
// issue-585) e solo se il campo non è già stato impostato (vale old() dopo
// un errore di validazione, o il valore esistente in edit).
const initializeFuelTypeDefault = () => {
    const vehicleSelect = document.getElementById('vehicle_id');
    const fuelTypeSelect = document.getElementById('fuel_type');

    if (!vehicleSelect || !fuelTypeSelect || fuelTypeSelect.value) {
        return;
    }

    vehicleSelect.addEventListener('change', () => {
        if (fuelTypeSelect.value) {
            return;
        }

        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
        const vehicleFuelType = selectedOption?.dataset.fuelType;

        if (vehicleFuelType && vehicleFuelType !== 'ibrido') {
            fuelTypeSelect.value = vehicleFuelType;
        }
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initializeFuelCardSelect();
    initializeFuelTypeDefault();
});
