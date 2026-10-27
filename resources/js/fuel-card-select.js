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

document.addEventListener('DOMContentLoaded', initializeFuelCardSelect);
