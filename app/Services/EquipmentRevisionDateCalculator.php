<?php

namespace App\Services;

use App\Models\EquipmentType;
use Illuminate\Support\Carbon;

/**
 * Calcolo automatico delle date di scadenza revisione/collaudo quando non
 * fornite esplicitamente, in base agli intervalli periodici del tipo di
 * attrezzatura. Estratto da EquipmentController (usato sia da store()/
 * update() sia da EquipmentCollaudoController) per tenere il primo sotto
 * le 400 righe (audit 2026-10-19, C12).
 */
class EquipmentRevisionDateCalculator
{
    /**
     * Calcola automaticamente la data di scadenza se non fornita, in base
     * all'intervallo di revisione periodica del tipo di attrezzatura.
     */
    public static function resolveExpirationDate(array $data): array
    {
        // Se l'utente ha già fornito una data di scadenza, la rispettiamo.
        if (! empty($data['expiration_date'])) {
            return $data;
        }

        // Serve una data di revisione e un tipo con intervallo periodico.
        if (empty($data['revision_date']) || empty($data['equipment_type_id'])) {
            return $data;
        }

        $equipmentType = EquipmentType::find($data['equipment_type_id']);
        $regularMonths = $equipmentType?->regular_inspection_months;

        if (! $regularMonths || $regularMonths <= 0) {
            return $data;
        }

        $data['expiration_date'] = Carbon::parse($data['revision_date'])
            ->addMonthsNoOverflow((int) $regularMonths)
            ->toDateString();

        return $data;
    }

    /**
     * Calcola automaticamente la data del prossimo collaudo se non fornita,
     * in base all'intervallo di collaudo del tipo di attrezzatura
     * (rilevante solo per gli estintori).
     */
    public static function resolveNextCollaudoDate(array $data): array
    {
        if (! empty($data['next_collaudo_date'])) {
            return $data;
        }

        if (empty($data['collaudo_date']) || empty($data['equipment_type_id'])) {
            return $data;
        }

        $equipmentType = EquipmentType::find($data['equipment_type_id']);
        $collaudoMonths = $equipmentType?->collaudo_interval_months;

        if (! $collaudoMonths || $collaudoMonths <= 0) {
            return $data;
        }

        $data['next_collaudo_date'] = Carbon::parse($data['collaudo_date'])
            ->addMonthsNoOverflow((int) $collaudoMonths)
            ->toDateString();

        return $data;
    }
}
