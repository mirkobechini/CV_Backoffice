<?php

namespace App\Policies;

use App\Models\Tire;
use App\Policies\Concerns\HasGroupScopedAccess;

class TirePolicy
{
    use HasGroupScopedAccess;
}
