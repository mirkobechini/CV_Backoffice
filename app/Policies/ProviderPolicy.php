<?php

namespace App\Policies;

use App\Models\Provider;
use App\Policies\Concerns\HasRoleBasedAccess;

class ProviderPolicy
{
    use HasRoleBasedAccess;
}
