<?php

namespace App\Policies;

use App\Models\MileageLog;
use App\Policies\Concerns\HasRoleBasedAccess;

class MileageLogPolicy
{
    use HasRoleBasedAccess;
}
