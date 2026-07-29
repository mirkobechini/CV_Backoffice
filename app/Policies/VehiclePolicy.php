<?php

namespace App\Policies;

use App\Models\Vehicle;
use App\Policies\Concerns\HasRoleBasedAccess;

class VehiclePolicy
{
    use HasRoleBasedAccess;
}
