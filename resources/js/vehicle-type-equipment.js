// Gestisce dinamicamente la sezione "Equipaggiamento necessario"
// nei form di creazione e modifica del tipo veicolo.
const initializeVehicleTypeEquipmentManager = () => {
    const addEquipmentButton = document.getElementById('add-equipment-btn');
    const equipmentRowsContainer = document.getElementById('equipment-rows');

    // Se il form corrente non contiene questa sezione, non facciamo nulla.
    if (!addEquipmentButton || !equipmentRowsContainer) {
        return;
    }

    // Le opzioni arrivano dalla Blade tramite data-attribute JSON.
    const equipmentOptions = JSON.parse(equipmentRowsContainer.dataset.equipmentOptions ?? '[]');
    const equipmentSelectSelector = 'select[name="required_equipment_types[]"]';
    const equipmentQuantitySelector = 'input[name="required_equipment_types_qty[]"]';

    // Mantiene un id univoco e ordinato sui select.
    const refreshSelectIds = () => {
        equipmentRowsContainer.querySelectorAll(equipmentSelectSelector).forEach((selectElement, index) => {
            selectElement.id = `required_equipment_types_${index}`;
        });
    };

    // Restituisce tutti gli equipaggiamenti già selezionati nelle altre righe.
    // `currentSelect` serve per non confrontare il select con sé stesso.
    const collectSelectedEquipmentValues = (currentSelect = null) => {
        return new Set(
            Array.from(equipmentRowsContainer.querySelectorAll(equipmentSelectSelector))
                .filter((selectElement) => selectElement !== currentSelect && selectElement.value !== '')
                .map((selectElement) => selectElement.value)
        );
    };

    // Costruisce l'HTML delle option, eventualmente pre-selezionando un valore.
    const buildEquipmentOptionsMarkup = (selectedValue = '') => {
        const placeholderOption = `<option value="" disabled ${selectedValue === '' ? 'selected' : ''}>Seleziona equipaggiamento</option>`;
        const renderedOptions = equipmentOptions
            .map((equipmentOption) => {
                const isSelected = String(equipmentOption.id) === String(selectedValue);
                return `<option value="${equipmentOption.id}"${isSelected ? ' selected' : ''}>${equipmentOption.name}</option>`;
            })
            .join('');

        return `${placeholderOption}${renderedOptions}`;
    };

    // Disabilita nei select le opzioni già scelte altrove
    // e blocca il pulsante "aggiungi" se non ci sono più elementi disponibili.
    const refreshEquipmentAvailability = () => {
        const equipmentSelects = equipmentRowsContainer.querySelectorAll(equipmentSelectSelector);

        equipmentSelects.forEach((selectElement) => {
            const valuesSelectedInOtherRows = collectSelectedEquipmentValues(selectElement);

            Array.from(selectElement.options).forEach((optionElement) => {
                if (optionElement.value === '') {
                    optionElement.disabled = true;
                    return;
                }

                const isAlreadyUsedElsewhere = valuesSelectedInOtherRows.has(optionElement.value);
                const isCurrentValue = optionElement.value === selectElement.value;

                optionElement.disabled = isAlreadyUsedElsewhere && !isCurrentValue;
            });
        });

        const selectedEquipmentCount = Array.from(collectSelectedEquipmentValues()).length;
        const noMoreAvailableOptions = selectedEquipmentCount >= equipmentOptions.length;

        addEquipmentButton.disabled = noMoreAvailableOptions;
        addEquipmentButton.classList.toggle('disabled', noMoreAvailableOptions);
    };

    // Crea una nuova riga composta da select, quantità e pulsante di rimozione.
    const appendEquipmentRow = (selectedValue = '', quantity = 1) => {
        const equipmentRow = document.createElement('div');
        equipmentRow.className = 'equipment-row d-flex gap-2 mb-2';
        equipmentRow.innerHTML = `
            <select class="form-select" name="required_equipment_types[]">
                ${buildEquipmentOptionsMarkup(selectedValue)}
            </select>
            <input type="number" class="form-control" name="required_equipment_types_qty[]" value="${quantity}" min="0">
            <button type="button" class="btn btn-outline-danger remove-equipment-btn">Rimuovi</button>
        `;

        equipmentRowsContainer.appendChild(equipmentRow);
        refreshSelectIds();
        refreshEquipmentAvailability();
    };

    // Aggiunge una riga vuota quando si clicca sul bottone.
    addEquipmentButton.addEventListener('click', () => {
        if (addEquipmentButton.disabled) {
            return;
        }

        appendEquipmentRow();
    });

    // Controlla che non venga scelto lo stesso equipaggiamento due volte.
    equipmentRowsContainer.addEventListener('change', (event) => {
        if (!event.target.matches(equipmentSelectSelector)) {
            return;
        }

        const currentSelect = event.target;
        const selectedValue = currentSelect.value;

        if (selectedValue !== '' && collectSelectedEquipmentValues(currentSelect).has(selectedValue)) {
            currentSelect.value = '';
            currentSelect.setCustomValidity('Questo equipaggiamento è già stato selezionato.');
            currentSelect.reportValidity();
        }

        currentSelect.setCustomValidity('');
        refreshEquipmentAvailability();
    });

    // Gestisce la rimozione di una riga.
    // Se è rimasta una sola riga, la resetta invece di eliminarla del tutto.
    equipmentRowsContainer.addEventListener('click', (event) => {
        const removeButton = event.target.closest('.remove-equipment-btn');

        if (!removeButton) {
            return;
        }

        const equipmentRows = equipmentRowsContainer.querySelectorAll('.equipment-row');
        const currentRow = removeButton.closest('.equipment-row');

        if (equipmentRows.length === 1) {
            currentRow.querySelector(equipmentSelectSelector).value = '';
            currentRow.querySelector(equipmentQuantitySelector).value = 1;
            refreshEquipmentAvailability();
            return;
        }

        currentRow.remove();
        refreshSelectIds();
        refreshEquipmentAvailability();
    });

    // Allinea lo stato iniziale quando la pagina viene caricata.
    refreshSelectIds();
    refreshEquipmentAvailability();
};

// Avvia la logica quando il DOM è pronto.
document.addEventListener('DOMContentLoaded', initializeVehicleTypeEquipmentManager);
