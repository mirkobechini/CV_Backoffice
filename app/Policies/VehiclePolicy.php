<?php

namespace App\Policies;

use App\Models\Vehicle;
use App\Policies\Concerns\HasGroupScopedAccess;

class VehiclePolicy
{
    use HasGroupScopedAccess;
}
