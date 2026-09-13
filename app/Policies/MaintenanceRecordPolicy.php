<?php

namespace App\Policies;

use App\Models\MaintenanceRecord;
use App\Policies\Concerns\HasGroupScopedAccess;

class MaintenanceRecordPolicy
{
    use HasGroupScopedAccess;
}
