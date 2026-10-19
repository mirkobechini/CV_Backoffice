<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use App\Services\DeadlineDueDateCalculator;
use App\Services\DeadlineStatusCalculator;
use App\Services\DeadlineStatusSyncService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class Deadline extends Model
{
    use HasActivity, Searchable, SoftDeletes;

    /**
     * Cache dell'accessor automatic_status per evitare query ripetute
     * quando l'accessor viene chiamato più volte (status_color, status_label,
     * view, grouping) sullo stesso modello.
     */
    protected ?string $automaticStatusCache = null;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    // status
    public const STATUS_PENDING = 'pending';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_RENEWED = 'renewed';

    public const STATUS_VALID = 'valid';

    // type
    public const TYPE_ASSICURAZIONE = 'Assicurazione';

    public const TYPE_MINISTERIAL = 'Revisione Ministeriale';

    public const TYPE_OXYGEN = 'Revisione Impianto Ossigeno';

    public const TYPE_TAGLIANDO = 'Tagliando';

    public const TYPE_CINGHIA = 'Cinghia Distribuzione';

    public const OXYGEN_CHECK_INTERVAL_MONTHS = 12;

    public const TIMING_BELT_INTERVAL_DAYS = 3650; // 10 anni

    public const TIMING_BELT_INTERVAL_KM = 100000; // 100.000 km

    public const TAGLIANDO_INTERVAL_MONTHS = 12; // 1 anno

    public const INSURANCE_INTERVAL_MONTHS = 12; // 1 anno (default se insurance_renewal_months non impostato)

    /**
     * Vocabolario fisso per insurance_coverage_type: prima era testo
     * libero (es. "RCA" vs "rca" vs "Responsabilità Civile"), che rendeva
     * impossibile raggruppare/filtrare per tipo di copertura in modo
     * affidabile.
     */
    public const INSURANCE_COVERAGE_TYPES = [
        'RCA',
        'Kasko',
        'Furto',
        'Incendio',
        'Cristalli',
        'Assistenza stradale',
        'Infortuni conducente',
        'Tutela legale',
        'Eventi speciali',
        'Globale complementari',
        'Altro',
    ];

    protected $fillable = [
        'vehicle_id',
        'type',
        'due_date',
        'status',
        'is_renewed',
        'renews_deadline_id',
        'interval_km',
        'last_mileage',
        'interval_days',
        'insurance_company',
        'insurance_policy_number',
        'insurance_coverage_limit',
        'insurance_broker_contact',
        'insurance_renewal_months',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'is_renewed' => 'boolean',
        'interval_km' => 'integer',
        'last_mileage' => 'integer',
        'interval_days' => 'integer',
        'insurance_coverage_limit' => 'decimal:2',
        'insurance_renewal_months' => 'integer',
    ];

    protected $searchable = ['type', 'status'];

    public function scopeUpcoming(Builder $query, int $days = 30): Builder
    {
        return $query->where('is_renewed', false)
            ->where('due_date', '>=', Carbon::today())
            ->where('due_date', '<=', Carbon::today()->addDays($days))
            ->orderBy('due_date');
    }

    /**
     * Pre-filtro SQL sicuro (sovrainsieme) per le scadenze che POTREBBERO
     * essere scadute secondo automatic_status: riduce le righe da
     * caricare prima del filtro PHP esatto (che resta l'unica fonte di
     * verità, serve il chilometraggio attuale del veicolo), invece di
     * caricare sempre tutte le scadenze non rinnovate del gruppo (audit
     * 2026-10-19, C6). Una scadenza scaduta ha sempre o una data passata,
     * o un tracciamento a km (l'unico caso che questo scope non può
     * escludere con certezza senza il km del veicolo) — non esclude mai
     * una scadenza che risulterebbe davvero scaduta.
     */
    public function scopePossiblyExpired(Builder $query): Builder
    {
        $today = Carbon::today()->toDateString();

        return $query->where(function (Builder $q) use ($today) {
            $q->whereDate('due_date', '<', $today)
                ->orWhere(function (Builder $q2) {
                    $q2->whereNotNull('interval_km')->whereNotNull('last_mileage');
                });
        });
    }

    public function maintenanceRecordItems()
    {
        return $this->morphMany(MaintenanceRecordItem::class, 'itemable');
    }

    public function insuranceCoverages()
    {
        return $this->hasMany(DeadlineInsuranceCoverage::class);
    }

    /**
     * Somma dei costi delle coperture collegate (0 se nessuna): sostituisce
     * la vecchia colonna singola insurance_premium, ora che una polizza può
     * avere più coperture insieme, ciascuna con il proprio costo.
     */
    public function getInsurancePremiumTotalAttribute(): float
    {
        return (float) $this->insuranceCoverages->sum('cost');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * La scadenza precedente che questa ha automaticamente rinnovato alla
     * creazione (es. la vecchia Revisione Ministeriale chiusa quando ne è
     * stata creata una nuova per lo stesso veicolo).
     */
    public function renewsDeadline()
    {
        return $this->belongsTo(Deadline::class, 'renews_deadline_id');
    }

    /**
     * La scadenza successiva che ha rinnovato questa (relazione inversa).
     */
    public function renewedByDeadline()
    {
        return $this->hasOne(Deadline::class, 'renews_deadline_id');
    }

    /**
     * L'intera catena di rinnovi di questa scadenza, dalla più vecchia alla
     * più recente: percorre renewsDeadline all'indietro e renewedByDeadline
     * in avanti, non solo il vicino immediato (quello già mostrato dai link
     * "Rinnova"/"Rinnovata da"). Include questa stessa scadenza.
     *
     * @return Collection<int, Deadline>
     */
    public function getRenewalChainAttribute(): Collection
    {
        $chain = collect([$this]);

        $current = $this;
        while ($current->renewsDeadline) {
            $current = $current->renewsDeadline;
            $chain->prepend($current);
        }

        $current = $this;
        while ($current->renewedByDeadline) {
            $current = $current->renewedByDeadline;
            $chain->push($current);
        }

        return $chain;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->automatic_status) {
            self::STATUS_EXPIRED => 'red',
            self::STATUS_PENDING => 'yellow',
            self::STATUS_RENEWED => 'green',
            self::STATUS_VALID => 'green',
            default => 'blue',
        };
    }

    /**
     * Le assicurazioni hanno un giorno di scadenza preciso (polizza che
     * rinnova a una data esatta), le altre tipologie solo mese/anno
     * (revisioni/tagliando/cinghia, calcolate o impostate a livello di mese).
     */
    public function getDueDateFormattedAttribute(): ?string
    {
        if (! $this->due_date) {
            return null;
        }

        return $this->type === self::TYPE_ASSICURAZIONE
            ? $this->due_date->format('d/m/Y')
            : $this->due_date->format('m/Y');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->automatic_status) {
            self::STATUS_RENEWED => 'Rinnovata',
            self::STATUS_PENDING => 'In scadenza',
            self::STATUS_EXPIRED => 'Scaduta',
            self::STATUS_VALID => 'Valida',
            default => 'Sconosciuto',
        };
    }

    /**
     * Slug del tipo di scadenza, usato per le classi CSS dei dot colorati
     * (es. "Tagliando" → "tagliando", "Cinghia Distribuzione" → "cinghia").
     */
    public function getTypeSlugAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_TAGLIANDO => 'tagliando',
            self::TYPE_CINGHIA => 'cinghia',
            self::TYPE_MINISTERIAL => 'revisione',
            self::TYPE_OXYGEN => 'ossigeno',
            default => Str::slug($this->type),
        };
    }

    /**
     * Etichetta dei giorni/km mancanti alla scadenza, per la colonna
     * "prossima scadenza" dell'index veicoli.
     */
    public function getDaysLabelAttribute(): string
    {
        // Scadenze per km: mostra i km mancanti al target
        if ($this->interval_km !== null && $this->last_mileage !== null) {
            $this->loadMissing('vehicle.latestMileageLog');
            $currentMileage = $this->vehicle?->mileage;
            if ($currentMileage !== null) {
                $remaining = ($this->last_mileage + $this->interval_km) - $currentMileage;
                if ($remaining <= 0) {
                    return '+'.number_format(abs($remaining), 0, ',', '.').' km';
                }

                return number_format($remaining, 0, ',', '.').' km';
            }
        }

        // Scadenze per data: mostra i giorni alla scadenza
        if ($this->due_date) {
            return $this->formatDaysRemaining($this->due_date);
        }

        return '—';
    }

    /**
     * Etichetta dei giorni mancanti/scaduti rispetto a due_date, indipendente
     * dal conteggio km. Usata per mostrare il badge "giorni" accanto a quello
     * "km" quando la scadenza ha entrambe le condizioni (es. Tagliando).
     */
    public function getDateRemainingLabelAttribute(): ?string
    {
        return $this->due_date ? $this->formatDaysRemaining($this->due_date) : null;
    }

    /**
     * "+N gg" se la data è già passata (scaduta da N giorni), "N gg"
     * altrimenti — condiviso tra days_label e date_remaining_label, che
     * prima duplicavano lo stesso calcolo (bug/DRY segnalato 2026-10-19).
     */
    private function formatDaysRemaining(Carbon $date): string
    {
        $days = Carbon::today()->diffInDays($date, false);

        return $days < 0 ? '+'.abs($days).' gg' : $days.' gg';
    }

    /**
     * Etichetta dei km mancanti/superati rispetto al target, indipendente
     * dalla data. Usata per mostrare il badge "km" accanto a quello "giorni".
     */
    public function getKmRemainingLabelAttribute(): ?string
    {
        if ($this->interval_km === null || $this->last_mileage === null) {
            return null;
        }

        $this->loadMissing('vehicle.latestMileageLog');
        $currentMileage = $this->vehicle?->mileage;
        if ($currentMileage === null) {
            return null;
        }

        $remaining = ($this->last_mileage + $this->interval_km) - $currentMileage;
        $formatted = number_format(abs($remaining), 0, ',', '.');

        return $remaining <= 0 ? '+'.$formatted.' km' : $formatted.' km';
    }

    public function getAutomaticStatusAttribute(): string
    {
        // Cachea il risultato: l'accessor può essere chiamato più volte
        // (status_color, status_label, view, grouping) e il calcolo può
        // comportare query sul veicolo/km.
        if ($this->automaticStatusCache !== null) {
            return $this->automaticStatusCache;
        }

        // Se marcata manualmente come rinnovata, preserviamo quel valore.
        if ($this->is_renewed) {
            return $this->automaticStatusCache = self::STATUS_RENEWED;
        }

        $currentMileage = null;
        if ($this->interval_km !== null && $this->last_mileage !== null) {
            $this->loadMissing('vehicle.latestMileageLog');
            $currentMileage = $this->vehicle?->mileage;
        }

        return $this->automaticStatusCache = DeadlineStatusCalculator::calculateDisplayStatus(
            $this->due_date,
            $this->interval_km,
            $this->last_mileage,
            $currentMileage,
            max(0, (int) config('deadlines.warning_months', 3)),
        );
    }

    /**
     * Sincronizza lo stato di più scadenze in un'unica passata.
     * Logica in DeadlineStatusSyncService (estratta per tenere questo
     * model sotto le 400 righe), nome/firma invariati per non toccare i
     * chiamanti esistenti.
     *
     * @param  Collection<int, Deadline>|iterable  $deadlines
     */
    public static function syncStatusesFromRules(iterable $deadlines): void
    {
        DeadlineStatusSyncService::syncMany($deadlines);
    }

    public function syncStatusFromRules(): void
    {
        DeadlineStatusSyncService::syncOne($this);
    }

    // Wrapper statici: logica in DeadlineDueDateCalculator (estratta per
    // tenere questo model sotto le 400 righe), nome/firma invariati per non
    // toccare i chiamanti esistenti (DeadlineService, VehicleObserver, ...).

    public static function calculateMinisterialDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        return DeadlineDueDateCalculator::calculateMinisterialDueDateForVehicle($vehicle, $excludeDeadlineId);
    }

    public static function calculateOxygenDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        return DeadlineDueDateCalculator::calculateOxygenDueDateForVehicle($vehicle, $excludeDeadlineId);
    }

    public static function supportsOxygenCheckForVehicle(Vehicle $vehicle): bool
    {
        return DeadlineDueDateCalculator::supportsOxygenCheckForVehicle($vehicle);
    }

    /**
     * Calcola la data di scadenza del prossimo tagliando.
     * Usa l'ultimo tagliando rinnovato come base, altrimenti l'immatricolazione.
     */
    public static function calculateTagliandoDueDateForVehicle(Vehicle $vehicle, ?int $excludeDeadlineId = null): ?Carbon
    {
        return DeadlineDueDateCalculator::calculateTagliandoDueDateForVehicle($vehicle, $excludeDeadlineId);
    }
}
