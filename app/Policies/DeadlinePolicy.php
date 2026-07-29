<?php

namespace App\Policies;

use App\Models\Deadline;
use App\Policies\Concerns\HasRoleBasedAccess;

class DeadlinePolicy
{
    use HasRoleBasedAccess;
}
