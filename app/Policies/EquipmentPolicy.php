<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Policies\Concerns\HasRoleBasedAccess;

class EquipmentPolicy
{
    use HasRoleBasedAccess;
}
