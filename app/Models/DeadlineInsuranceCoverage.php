<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeadlineInsuranceCoverage extends Model
{
    protected $fillable = [
        'deadline_id',
        'coverage_type',
        'cost',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
    ];

    public function deadline()
    {
        return $this->belongsTo(Deadline::class);
    }
}
