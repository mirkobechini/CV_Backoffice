<?php

namespace App\Policies;

use App\Policies\Concerns\HasGroupScopedAccess;

class MaintenanceRecordPolicy
{
    use HasGroupScopedAccess;
}
