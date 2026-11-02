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

// Filtra le opzioni "Carburante" a quelle davvero registrate per il
// veicolo selezionato (Vehicle::allowedFuelLogTypes(), letto dall'attributo
// data-fuel-types di ciascuna option veicolo): un veicolo non ibrido ha
// un'unica opzione selezionabile, un ibrido solo quelle scelte in
// anagrafica (issue-585 follow-up). Se il veicolo non ha ancora nessun
// carburante configurato (es. ibrido non configurato), non blocca l'utente:
// mostra tutte le opzioni come fallback permissivo.
const initializeFuelTypeFilter = () => {
    const vehicleSelect = document.getElementById('vehicle_id');
    const fuelTypeSelect = document.getElementById('fuel_type');

    if (!vehicleSelect || !fuelTypeSelect) {
        return;
    }

    const fuelTypeOptions = Array.from(fuelTypeSelect.options).filter((option) => option.value !== '');

    const applyFilter = () => {
        const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
        const allowed = (selectedOption?.dataset.fuelTypes || '').split(',').filter(Boolean);
        const showAll = allowed.length === 0;
        const previousValue = fuelTypeSelect.value;

        let visibleCount = 0;
        let onlyVisibleValue = null;

        fuelTypeOptions.forEach((option) => {
            const isVisible = showAll || allowed.includes(option.value);
            option.hidden = !isVisible;
            option.disabled = !isVisible;
            if (isVisible) {
                visibleCount += 1;
                onlyVisibleValue = option.value;
            }
        });

        if (previousValue && !showAll && !allowed.includes(previousValue)) {
            fuelTypeSelect.value = '';
        }

        if (!fuelTypeSelect.value && visibleCount === 1) {
            fuelTypeSelect.value = onlyVisibleValue;
        }
    };

    vehicleSelect.addEventListener('change', applyFilter);
    applyFilter();
};

document.addEventListener('DOMContentLoaded', () => {
    initializeFuelCardSelect();
    initializeFuelTypeFilter();
});
