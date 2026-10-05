{{--
    Script condiviso tra create.blade.php ed edit.blade.php: filtro guasti/
    scadenze/gomme per veicolo selezionato, hint ultimo km noto, sezione
    "Cambio Gomme" e dialog di completamento. Identico nei due form, non
    dipende da quale azione (store/update) o id DOM-prefix usati: la
    selezione passa sempre per le classi (.issue-checkbox ecc.), mai per id
    letterali.
--}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const vehicleSelect = document.getElementById('vehicle_id');
        const issueSection = document.getElementById('issue-section');
        const noIssueCta = document.getElementById('no-issue-cta');
        const createIssueLink = document.getElementById('create-issue-link');
        const deadlineSection = document.getElementById('deadline-section');
        const noIssueMsg = document.getElementById('no-issue-msg');
        const noDeadlineMsg = document.getElementById('no-deadline-msg');
        const closedIssueSection = document.getElementById('closed-issue-section');
        const noClosedIssueMsg = document.getElementById('no-closed-issue-msg');

        const filterByVehicle = () => {
            const selectedVehicleId = vehicleSelect.value;

            const issueChecks = document.querySelectorAll('.issue-checkbox');
            let hasVisibleIssue = false;
            issueChecks.forEach(el => {
                if (el.dataset.vehicleId === selectedVehicleId) {
                    el.style.display = '';
                    hasVisibleIssue = true;
                } else {
                    el.style.display = 'none';
                    el.querySelector('input').checked = false;
                }
            });

            if (!selectedVehicleId) {
                issueSection.style.display = 'none';
                noIssueCta.style.display = 'none';
                noIssueMsg.style.display = 'none';
            } else if (!hasVisibleIssue) {
                issueSection.style.display = '';
                noIssueCta.style.display = '';
                noIssueMsg.style.display = '';
                createIssueLink.href =
                    `{{ route('admin.issues.create') }}?vehicle_id=${selectedVehicleId}&back={{ urlencode(url()->full()) }}`;
            } else {
                issueSection.style.display = '';
                noIssueCta.style.display = 'none';
                noIssueMsg.style.display = 'none';
            }

            const closedIssueChecks = document.querySelectorAll('.closed-issue-checkbox');
            let hasVisibleClosedIssue = false;
            closedIssueChecks.forEach(el => {
                if (el.dataset.vehicleId === selectedVehicleId) {
                    el.style.display = '';
                    hasVisibleClosedIssue = true;
                } else {
                    el.style.display = 'none';
                    el.querySelector('input').checked = false;
                }
            });

            if (!closedIssueSection) {
                // Sezione non presente: nessun guasto risolto disponibile.
            } else if (!selectedVehicleId || !hasVisibleClosedIssue) {
                closedIssueSection.style.display = 'none';
                noClosedIssueMsg.style.display = 'none';
            } else {
                closedIssueSection.style.display = '';
                noClosedIssueMsg.style.display = 'none';
            }

            const deadlineChecks = document.querySelectorAll('.deadline-checkbox');
            let hasVisibleDeadline = false;
            deadlineChecks.forEach(el => {
                if (el.dataset.vehicleId === selectedVehicleId) {
                    el.style.display = '';
                    hasVisibleDeadline = true;
                } else {
                    el.style.display = 'none';
                    el.querySelector('input').checked = false;
                }
            });

            if (!selectedVehicleId) {
                deadlineSection.style.display = 'none';
                noDeadlineMsg.style.display = 'none';
            } else if (!hasVisibleDeadline) {
                deadlineSection.style.display = '';
                noDeadlineMsg.style.display = '';
            } else {
                deadlineSection.style.display = '';
                noDeadlineMsg.style.display = 'none';
            }
        };

        filterByVehicle();
        vehicleSelect.addEventListener('change', filterByVehicle);

        // Mostra l'ultimo km noto del veicolo selezionato come placeholder
        // e come promemoria sotto il campo, per aiutare a non inserire un
        // valore incoerente con la cronologia (vedi anche la validazione
        // server-side in Store/UpdateMaintenanceRecordRequest).
        const mileageInput = document.getElementById('mileage_at_service');
        const mileageHint = document.getElementById('mileage-last-known-hint');
        const updateMileageHint = () => {
            const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
            const lastMileage = selectedOption?.dataset.mileage;
            const lastMileageDate = selectedOption?.dataset.mileageDate;

            if (lastMileage) {
                mileageInput.placeholder = lastMileage;
                mileageHint.textContent = lastMileageDate
                    ? `{{ __('Ultimo km noto') }}: ${Number(lastMileage).toLocaleString('it-IT')} km ({{ __('il') }} ${lastMileageDate})`
                    : `{{ __('Ultimo km noto') }}: ${Number(lastMileage).toLocaleString('it-IT')} km`;
            } else {
                mileageInput.placeholder = 'es. 87400';
                mileageHint.textContent = '';
            }
        };
        updateMileageHint();
        vehicleSelect.addEventListener('change', updateMileageHint);

        // --- Sezione "Cambio Gomme" ---
        const activityTypeSelect = document.getElementById('activity_type');
        const tireSection = document.getElementById('tire-section');
        const noTireMsg = document.getElementById('no-tire-msg');

        const toggleTireSection = () => {
            tireSection.style.display = activityTypeSelect.value === 'Cambio Gomme' ? '' : 'none';
        };
        const filterTiresByVehicle = () => {
            const selectedVehicleId = vehicleSelect.value;
            const tireChecks = document.querySelectorAll('.tire-checkbox');
            let hasVisibleTire = false;
            tireChecks.forEach(el => {
                if (el.dataset.vehicleId === selectedVehicleId) {
                    el.style.display = '';
                    hasVisibleTire = true;
                } else {
                    el.style.display = 'none';
                    el.querySelector('input').checked = false;
                }
            });
            noTireMsg.style.display = (selectedVehicleId && !hasVisibleTire) ? '' : 'none';
        };

        toggleTireSection();
        filterTiresByVehicle();
        activityTypeSelect.addEventListener('change', toggleTireSection);
        vehicleSelect.addEventListener('change', filterTiresByVehicle);

        // Mostra il selettore posizione solo quando si descrive una gomma singola.
        const newTireGroupSelect = document.getElementById('new_tire_group');
        const newTirePositionField = document.getElementById('new-tire-position-field');
        const toggleNewTirePositionField = () => {
            newTirePositionField.style.display = newTireGroupSelect.value === 'single' ? '' : 'none';
        };
        toggleNewTirePositionField();
        newTireGroupSelect.addEventListener('change', toggleNewTirePositionField);

        // --- Dialog completamento ---
        const form = document.getElementById('maintenance-record-form');
        const returnDateInput = document.querySelector('input[name="return_date"]');
        const completionModalEl = document.getElementById('completionModal');
        const completionModal = new bootstrap.Modal(completionModalEl);
        const completionIssuesList = document.getElementById('completion-issues-list');
        const completionDeadlinesList = document.getElementById('completion-deadlines-list');
        const completionConfirmBtn = document.getElementById('completion-confirm-btn');

        const issueData = [];
        document.querySelectorAll('.issue-checkbox, .closed-issue-checkbox').forEach(el => {
            const input = el.querySelector('input');
            issueData.push({
                id: input.value,
                label: el.querySelector('label').textContent.trim(),
                vehicleId: el.dataset.vehicleId,
            });
        });
        const deadlineData = [];
        document.querySelectorAll('.deadline-checkbox').forEach(el => {
            const input = el.querySelector('input');
            deadlineData.push({
                id: input.value,
                label: el.querySelector('label').textContent.trim(),
                vehicleId: el.dataset.vehicleId,
            });
        });

        let pendingSubmit = false;

        form.addEventListener('submit', function(e) {
            const returnDate = returnDateInput ? returnDateInput.value : '';

            if (!returnDate || pendingSubmit) {
                return; // nessun dialog, submit normale
            }

            const selectedIssues = issueData.filter(d => {
                const cb = document.querySelector(`input[name="issue_ids[]"][value="${d.id}"]`);
                return cb && cb.checked;
            });
            const selectedDeadlines = deadlineData.filter(d => {
                const cb = document.querySelector(`input[name="deadline_ids[]"][value="${d.id}"]`);
                return cb && cb.checked;
            });

            completionIssuesList.innerHTML = '';
            if (selectedIssues.length === 0) {
                document.getElementById('completion-issues').style.display = 'none';
            } else {
                document.getElementById('completion-issues').style.display = '';
                selectedIssues.forEach(issue => {
                    const div = document.createElement('div');
                    div.className = 'form-check';
                    div.innerHTML = `
                        <input class="form-check-input completion-issue" type="checkbox"
                            value="${issue.id}" id="comp_issue_${issue.id}">
                        <label class="form-check-label" for="comp_issue_${issue.id}">${issue.label}</label>`;
                    completionIssuesList.appendChild(div);
                });
            }

            completionDeadlinesList.innerHTML = '';
            if (selectedDeadlines.length === 0) {
                document.getElementById('completion-deadlines').style.display = 'none';
            } else {
                document.getElementById('completion-deadlines').style.display = '';
                selectedDeadlines.forEach(deadline => {
                    const div = document.createElement('div');
                    div.className = 'form-check';
                    div.innerHTML = `
                        <input class="form-check-input completion-deadline" type="checkbox"
                            value="${deadline.id}" id="comp_deadline_${deadline.id}">
                        <label class="form-check-label" for="comp_deadline_${deadline.id}">${deadline.label}</label>`;
                    completionDeadlinesList.appendChild(div);
                });
            }

            e.preventDefault();
            pendingSubmit = true;
            completionModal.show();
        });

        completionConfirmBtn.addEventListener('click', function() {
            form.querySelectorAll('input[name="completed_issue_ids[]"], input[name="completed_deadline_ids[]"]')
                .forEach(el => el.remove());

            document.querySelectorAll('.completion-issue:checked').forEach(cb => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'completed_issue_ids[]';
                hidden.value = cb.value;
                form.appendChild(hidden);
            });
            document.querySelectorAll('.completion-deadline:checked').forEach(cb => {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'completed_deadline_ids[]';
                hidden.value = cb.value;
                form.appendChild(hidden);
            });

            completionModal.hide();
            pendingSubmit = false;
            form.submit();
        });
    });
</script>
