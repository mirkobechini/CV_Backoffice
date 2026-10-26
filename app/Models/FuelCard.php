<?php

namespace App\Models;

use App\Models\Concerns\HasDirectGroupScoping;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class FuelCard extends Model
{
    use HasActivity;
    use HasDirectGroupScoping;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_JOLLY = 'jolly';

    public const STATUS_UNASSIGNED = 'unassigned';

    /**
     * Tessera sostituita da una nuova: a differenza di jolly/unassigned,
     * mantiene il vehicle_id (non obbligatorio né vietato, a differenza di
     * "assigned" dove è richiesto) per restare traccia di quale veicolo la
     * usava, invece di perdere quel legame una volta tolta dalla
     * circolazione.
     */
    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'card_number',
        'provider',
        'status',
        'vehicle_id',
        'group_id',
        'notes',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function fuelLogs()
    {
        return $this->hasMany(FuelLog::class);
    }

    /**
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_ASSIGNED, self::STATUS_JOLLY, self::STATUS_UNASSIGNED, self::STATUS_RETIRED];
    }

    /**
     * Etichetta breve (badge index/show). Unica fonte, prima duplicata nel
     * match di questo accessor senza passare da __() (audit 2026-10-22 Q8).
     *
     * @return array<string, string>
     */
    public static function statusBadgeLabels(): array
    {
        return [
            self::STATUS_ASSIGNED => __('Assegnata'),
            self::STATUS_JOLLY => __('Jolly (condivisa)'),
            self::STATUS_UNASSIGNED => __('Non assegnata'),
            self::STATUS_RETIRED => __('Vecchia (sostituita)'),
        ];
    }

    /**
     * Etichetta descrittiva usata nelle select dei form create/edit, prima
     * ripetuta identica in entrambe le viste (audit 2026-10-22 Q8).
     *
     * @return array<string, string>
     */
    public static function statusFormOptions(): array
    {
        return [
            self::STATUS_ASSIGNED => __('Assegnata a un veicolo'),
            self::STATUS_JOLLY => __('Jolly (condivisa tra tutti i veicoli)'),
            self::STATUS_UNASSIGNED => __('Non ancora assegnata'),
            self::STATUS_RETIRED => __('Vecchia (sostituita)'),
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusBadgeLabels()[$this->status] ?? __('Non assegnata');
    }
}
