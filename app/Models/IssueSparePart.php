<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\HasActivity;
use Spatie\Activitylog\Support\LogOptions;

class IssueSparePart extends Model
{
    use HasActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->logExcept(['notes']);
    }

    protected $fillable = [
        'issue_id',
        'name',
        'notes',
        'ordered_at',
        'arrived_at',
    ];

    protected $casts = [
        'ordered_at' => 'date',
        'arrived_at' => 'date',
    ];

    public function issue()
    {
        return $this->belongsTo(Issue::class);
    }
}
