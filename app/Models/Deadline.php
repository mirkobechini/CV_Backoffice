<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deadline extends Model
{
    protected $fillable = [
        'vehicle_id',
        'type',
        'due_date',
        'status',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function getDueDateFormattedAttribute(): ?string
    {
        return $this->due_date?->format('d/m/Y');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
