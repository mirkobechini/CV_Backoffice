<?php

namespace App\Policies;

use App\Models\EquipmentType;
use App\Policies\Concerns\HasRoleBasedAccess;

class EquipmentTypePolicy
{
    use HasRoleBasedAccess;
}
