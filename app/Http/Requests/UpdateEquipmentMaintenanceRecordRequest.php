<?php

namespace App\Http\Requests;

/**
 * Identica a StoreEquipmentMaintenanceRecordRequest, nessuna regola cambia
 * tra creazione e modifica (audit 2026-10-22, Q6): prima duplicata per
 * intero invece di estendere.
 */
class UpdateEquipmentMaintenanceRecordRequest extends StoreEquipmentMaintenanceRecordRequest {}
