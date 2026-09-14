<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Policies\Concerns\HasGroupScopedAccess;

class EquipmentPolicy
{
    use HasGroupScopedAccess;
}
