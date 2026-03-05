<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    protected $fillable = [
        'name',
        'first_inspection_months',
        'regular_inspection_months',
    ];
    public function equipments()
    {
        return $this->hasMany(Equipment::class);
    }
}
