<?php

namespace App\Policies;

use App\Models\VehicleType;
use App\Policies\Concerns\HasRoleBasedAccess;

class VehicleTypePolicy
{
    use HasRoleBasedAccess;
}
