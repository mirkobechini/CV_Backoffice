<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentType extends Model
{
    public const CATEGORY_FIRE_EXTINGUISHER = 'fire_extinguisher';

    public const CATEGORY_LSU = 'lsu';

    public const CATEGORY_CHAIR = 'chair';

    public const CATEGORY_STRETCHER = 'stretcher';

    public const CATEGORY_DAE = 'dae';

    public const CATEGORY_LUCAS = 'lucas';

    public const CATEGORY_LIFEPAK = 'lifepak';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_FIRE_EXTINGUISHER,
        self::CATEGORY_LSU,
        self::CATEGORY_CHAIR,
        self::CATEGORY_STRETCHER,
        self::CATEGORY_DAE,
        self::CATEGORY_LUCAS,
        self::CATEGORY_LIFEPAK,
        self::CATEGORY_OTHER,
    ];

    protected $fillable = [
        'name',
        'category',
        'first_inspection_months',
        'regular_inspection_months',
        'collaudo_interval_months',
        'max_revisions_before_exchange',
    ];

    public function getFirstInspectionMonthsFormattedAttribute(): ?string
    {
        return $this->first_inspection_months !== null
            ? $this->first_inspection_months . ' mesi'
            : null;
    }

    public function getRegularInspectionMonthsFormattedAttribute(): ?string
    {
        return $this->regular_inspection_months !== null
            ? $this->regular_inspection_months . ' mesi'
            : null;
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::categoryLabel($this->category);
    }

    public static function categoryLabel(?string $category): string
    {
        return match ($category) {
            self::CATEGORY_FIRE_EXTINGUISHER => 'Estintore',
            self::CATEGORY_LSU => 'Aspiratore (LSU)',
            self::CATEGORY_CHAIR => 'Sedia',
            self::CATEGORY_STRETCHER => 'Barella',
            self::CATEGORY_DAE => 'DAE',
            self::CATEGORY_LUCAS => 'LUCAS',
            self::CATEGORY_LIFEPAK => 'LIFEPAK',
            default => 'Altro',
        };
    }

    public function equipments()
    {
        return $this->hasMany(Equipment::class, 'equipment_type_id');
    }

    public function vehicleTypes()
    {
        return $this->belongsToMany(VehicleType::class, 'vehicle_type_equipment_requirements', 'equipment_type_id', 'vehicle_type_id')
            ->withPivot('required_quantity');
    }
}
