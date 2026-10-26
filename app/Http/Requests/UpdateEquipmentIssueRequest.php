<?php

namespace App\Http\Requests;

/**
 * Identica a StoreEquipmentIssueRequest, nessuna regola cambia tra
 * creazione e modifica (audit 2026-10-22, Q6): prima duplicata per intero
 * invece di estendere.
 */
class UpdateEquipmentIssueRequest extends StoreEquipmentIssueRequest {}
