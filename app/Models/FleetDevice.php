<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class FleetDevice extends Model
{
    use HasActivity;

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

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_TELEPASS => 'Telepass',
            self::TYPE_UNIPOL_MOVE => 'UnipolMove',
            default => 'Altro',
        };
    }
}
