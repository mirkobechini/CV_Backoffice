<?php

namespace App\Models\Concerns;

use App\Models\Deadline;
use App\Models\FuelCard;
use App\Models\FuelLog;
use App\Models\Tire;
use App\Models\TireChange;
use Illuminate\Support\Collection;

/**
 * Carburante, cinghia di distribuzione e pneumatici: estratto da Vehicle.php
 * (era a 485 righe, oltre la soglia ~400 di AGENTS.md) per non fargliela
 * superare — audit qualità 2026-11-25, F4.
 */
trait HasFuelAndTireAttributes
{
    public function getTimingBeltTypeLabelAttribute(): string
    {
        return match ($this->timing_belt_type) {
            self::TIMING_BELT_TYPE_DRY_BELT => 'Cinghia a secco',
            self::TIMING_BELT_TYPE_OIL_BATH_BELT => "Cinghia a bagno d'olio",
            default => 'Catena',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function fuelTypeLabels(): array
    {
        return [
            'benzina' => __('Benzina'),
            'diesel' => __('Diesel'),
            'elettrico' => __('Elettrico'),
            'ibrido' => __('Ibrido'),
        ];
    }

    /**
     * Colore badge per tipo di alimentazione, usato nelle viste rifornimento.
     *
     * @return array<string, string>
     */
    public static function fuelTypeBadgeColors(): array
    {
        return [
            'benzina' => 'yellow',
            'diesel' => 'blue',
            'elettrico' => 'green',
            'ibrido' => 'purple',
        ];
    }

    public function getFuelTypeLabelAttribute(): ?string
    {
        return $this->fuel_type ? (self::fuelTypeLabels()[$this->fuel_type] ?? $this->fuel_type) : null;
    }

    /**
     * Come fuel_type_label, ma per un ibrido con allowed_fuel_types
     * configurato aggiunge lo specifico tra parentesi (es. "Ibrido
     * (Benzina, Metano)"): "Ibrido" da solo in scheda veicolo non dice
     * quali carburanti ammette davvero.
     */
    public function getFuelTypeDisplayLabelAttribute(): ?string
    {
        if ($this->fuel_type !== 'ibrido' || empty($this->allowed_fuel_types)) {
            return $this->fuel_type_label;
        }

        $labels = FuelLog::fuelTypeLabels();
        $specifics = collect($this->allowed_fuel_types)->map(fn ($type) => $labels[$type] ?? $type)->implode(', ');

        return "{$this->fuel_type_label} ({$specifics})";
    }

    /**
     * Carburanti ammessi per un rifornimento (FuelLog) di questo veicolo:
     * per i veicoli non ibridi è sempre e solo il loro unico fuel_type; per
     * gli ibridi (es. benzina/metano) quelli scelti esplicitamente in
     * allowed_fuel_types — vuoto se non ancora configurato (issue-585
     * follow-up: Vehicle.fuel_type="ibrido" da solo non dice quali).
     *
     * @return array<int, string>
     */
    public function allowedFuelLogTypes(): array
    {
        if (! $this->fuel_type) {
            return [];
        }

        if ($this->fuel_type !== 'ibrido') {
            return [$this->fuel_type];
        }

        return $this->allowed_fuel_types ?? [];
    }

    /**
     * Elenco leggibile delle misure consigliate col relativo codice (es.
     * "A1: 225/75R16C"), lo stesso usato dalla carta di circolazione per
     * indicare quale misura è quale nella tabella delle combinazioni
     * ammesse. Usato nella show e nel PDF scheda veicolo, invece di
     * duplicare la stessa logica in due viste.
     */
    public function getAllowedTireSizesLabelAttribute(): string
    {
        return $this->allowedTireSizeLines()->implode(', ') ?: 'N/A';
    }

    /**
     * Righe "codice: misura" condivise tra l'accessor sopra (inline, per il
     * PDF) e la scheda veicolo (una per riga) — vedi
     * resources/views/components/admin/vehicle-show/tech-specs-card.blade.php.
     */
    public function allowedTireSizeLines(): Collection
    {
        return collect($this->allowed_tire_sizes)
            ->map(fn ($entry) => trim(($entry['code'] ?? '').': '.($entry['size'] ?? ''), ': '));
    }

    /**
     * Elenco leggibile delle combinazioni ammesse tra asse anteriore e
     * posteriore (es. "A1-A2, A2-A2"), la stessa tabella presente sulla
     * carta di circolazione — determina dove può andare ciascuna misura,
     * vedi Tire::sizeMismatchWarning().
     */
    public function getAllowedTireSizeCombinationsLabelAttribute(): string
    {
        if (empty($this->allowed_tire_size_combinations)) {
            return 'N/A';
        }

        return collect($this->allowed_tire_size_combinations)
            ->map(fn ($entry) => ($entry['front_code'] ?? '?').'-'.($entry['rear_code'] ?? '?'))
            ->implode(', ');
    }

    public function needsTimingBeltDeadline(): bool
    {
        return in_array($this->timing_belt_type, [
            self::TIMING_BELT_TYPE_DRY_BELT,
            self::TIMING_BELT_TYPE_OIL_BATH_BELT,
        ], true);
    }

    /**
     * Solo la cinghia a bagno d'olio ha anche una scadenza temporale: quella
     * a secco scade solo in base ai km percorsi, senza limite di tempo.
     */
    public function timingBeltIntervalDays(): ?int
    {
        return $this->timing_belt_type === self::TIMING_BELT_TYPE_OIL_BATH_BELT
            ? Deadline::TIMING_BELT_INTERVAL_DAYS
            : null;
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    public function fuelCards()
    {
        return $this->hasMany(FuelCard::class);
    }

    public function tires()
    {
        return $this->hasMany(Tire::class);
    }

    public function tireChanges()
    {
        return $this->hasMany(TireChange::class);
    }

    public function mountedTires()
    {
        return $this->tires()->where('status', Tire::STATUS_MOUNTED);
    }
}
