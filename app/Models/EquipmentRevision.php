<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EquipmentRevision extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public const KIND_REVISION = 'revision';

    public const KIND_COLLAUDO = 'collaudo';

    protected $fillable = [
        'equipment_id',
        'kind',
        'performed_date',
        'notes',
    ];

    protected $casts = [
        'performed_date' => 'date',
    ];

    public function equipment()
    {
        return $this->belongsTo(Equipment::class);
    }

    public function getPerformedDateFormattedAttribute(): ?string
    {
        return $this->performed_date?->format('d/m/Y');
    }

    public function getKindLabelAttribute(): string
    {
        return match ($this->kind) {
            self::KIND_COLLAUDO => 'Collaudo',
            default => 'Revisione',
        };
    }
}
