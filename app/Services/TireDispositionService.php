<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordItem;
use App\Models\Tire;
use Illuminate\Support\Collection;

/**
 * Gomme collegate a un appuntamento: collegamento (al salvataggio), scelta
 * della disposizione richiesta (al completamento) e montaggio vero e
 * proprio. Estratto da MaintenanceCompletionService per tenerlo sotto le
 * 400 righe (audit 2026-10-19) — il montaggio era inoltre duplicato quasi
 * identico tra complete() e processCompletedItems(), ora un solo metodo
 * condiviso (mountLinkedTires()).
 */
class TireDispositionService
{
    public function __construct(
        private readonly TireChangeService $tireChangeService,
    ) {}

    /**
     * Per un appuntamento "Cambio Gomme", collega le gomme da montare: una
     * o più esistenti (in magazzino, 1/2/4, validate in
     * ValidatesTireSelection) e/o di nuove appena descritte. Il montaggio
     * vero e proprio (con la scelta di cosa fare delle gomme sostituite)
     * avviene solo al completamento dell'appuntamento (vedi mountLinkedTires()),
     * non qui.
     */
    public function linkTireItems(MaintenanceRecord $maintenanceRecord, array $data): void
    {
        if (($data['activity_type'] ?? null) !== MaintenanceRecord::ACTIVITY_TIRE_CHANGE) {
            return;
        }

        $tires = collect();

        if (! empty($data['target_tire_ids'])) {
            $tires = Tire::whereIn('id', $data['target_tire_ids'])
                ->where('vehicle_id', $data['vehicle_id'])
                ->get();
        }

        if (! empty($data['new_tire_season'])) {
            $vehicle = $maintenanceRecord->vehicle;
            $warning = null;

            foreach (Tire::positionsForGroup($data['new_tire_group'] ?? null, $data['new_tire_position'] ?? null) as $position) {
                $tires->push(Tire::create([
                    'vehicle_id' => $data['vehicle_id'],
                    'season' => $data['new_tire_season'],
                    'position' => $position,
                    'brand' => $data['new_tire_brand'] ?? null,
                    'model_name' => $data['new_tire_model_name'] ?? null,
                    'size' => $data['new_tire_size'] ?? null,
                    'status' => Tire::STATUS_STORED,
                ]));

                $warning ??= Tire::sizeMismatchWarning($data['new_tire_size'] ?? null, $vehicle, $position);
            }

            if ($warning) {
                session()->flash('tire_size_warning', $warning);
            }
        }

        foreach ($tires as $tire) {
            $maintenanceRecord->items()->create([
                'itemable_id' => $tire->id,
                'itemable_type' => Tire::class,
                'completed' => false,
            ]);
        }
    }

    /**
     * Item gomma di questo appuntamento per cui il completamento chiederà
     * una disposizione (stored/retired): solo quelli non ancora completati
     * e per cui c'è davvero una gomma montata nella stessa posizione da
     * sostituire (altrimenti è un primo montaggio, nessuna scelta da fare).
     * Usata sia per costruire le regole di validazione in complete() sia
     * per il form del modale di completamento.
     *
     * @return Collection<int, MaintenanceRecordItem>
     */
    public function tireItemsRequiringDisposition(MaintenanceRecord $maintenanceRecord): Collection
    {
        $maintenanceRecord->loadMissing('items.itemable');

        return $maintenanceRecord->items
            ->where('itemable_type', Tire::class)
            ->where('completed', false)
            ->filter(fn ($item) => $item->itemable && $this->tireChangeService->findPreviousMountedTire($item->itemable));
    }

    /**
     * Monta le gomme collegate non ancora completate (uno o più set, es.
     * anteriori + posteriori insieme), applicando alle gomme sostituite la
     * disposizione scelta (magazzino o dismesse). Le chiamate in sequenza
     * si compongono correttamente anche quando un set "full" già montato
     * va diviso tra i due nuovi assi montati (vedi TireChangeService).
     * Condiviso tra MaintenanceCompletionService::complete() e
     * processCompletedItems() — prima duplicato quasi identico tra i due.
     */
    public function mountLinkedTires(MaintenanceRecord $maintenanceRecord, array $previousDisposition = []): void
    {
        $tireItems = $maintenanceRecord->items->where('itemable_type', Tire::class);

        foreach ($tireItems as $tireItem) {
            if ($tireItem->completed || ! $tireItem->itemable) {
                continue;
            }

            // Disposizione scelta indipendentemente per ogni gomma
            // sostituita (es. 1 dismessa e 3 in magazzino nello stesso
            // cambio): 'stored' come default innocuo quando non c'è nulla
            // da smontare in quella posizione (primo montaggio), perché in
            // quel caso non viene comunque applicato (vedi
            // TireChangeService::findPreviousMountedTire()).
            $disposition = $previousDisposition[$tireItem->itemable_id] ?? Tire::STATUS_STORED;

            $this->tireChangeService->recordChange(
                $tireItem->itemable,
                $maintenanceRecord->return_date,
                $maintenanceRecord->mileage_at_service,
                $disposition,
                "Appuntamento del {$maintenanceRecord->appointment_date_formatted}",
            );
            $tireItem->update(['completed' => true]);
        }
    }
}
