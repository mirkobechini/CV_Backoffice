<?php

namespace App\Policies;

use App\Models\Deadline;
use App\Policies\Concerns\HasGroupScopedAccess;

class DeadlinePolicy
{
    use HasGroupScopedAccess;
}
