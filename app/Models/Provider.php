<?php

namespace App\Models;

use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = [
        'name',
        'contact_info',
        'address',
        'type',
    ];

    protected $searchable = ['name', 'address', 'contact_info', 'type'];

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }
}
