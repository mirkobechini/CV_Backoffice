<?php

namespace App\Policies;

use App\Models\MaintenanceRecord;
use App\Policies\Concerns\HasRoleBasedAccess;

class MaintenanceRecordPolicy
{
    use HasRoleBasedAccess;
}
