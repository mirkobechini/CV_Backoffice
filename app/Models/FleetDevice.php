<?php

namespace App\Models;

use App\Models\Concerns\HasDirectGroupScoping;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class FleetDevice extends Model
{
    use HasActivity;
    use HasDirectGroupScoping;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const TYPE_TELEPASS = 'telepass';

    public const TYPE_UNIPOL_MOVE = 'unipol_move';

    public const TYPE_ALTRO = 'altro';

    protected $fillable = [
        'type',
        'code',
        'vehicle_id',
        'group_id',
        'provider_notes',
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

    public function assignments()
    {
        return $this->hasMany(FleetDeviceAssignment::class)->orderByDesc('assigned_date');
    }

    /**
     * Unica fonte per le etichette (prima duplicate tra questo accessor e
     * i due form create/edit, audit 2026-10-22 Q8): i brand (Telepass,
     * UnipolMove) restano non tradotti, solo "Altro" passa da __().
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_TELEPASS => 'Telepass',
            self::TYPE_UNIPOL_MOVE => 'UnipolMove',
            self::TYPE_ALTRO => __('Altro'),
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeOptions()[$this->type] ?? __('Altro');
    }
}
