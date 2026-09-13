<?php

namespace App\Policies;

use App\Models\MileageLog;
use App\Policies\Concerns\HasGroupScopedAccess;

class MileageLogPolicy
{
    use HasGroupScopedAccess;
}
