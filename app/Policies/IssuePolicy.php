<?php

namespace App\Policies;

use App\Models\Issue;
use App\Policies\Concerns\HasRoleBasedAccess;

class IssuePolicy
{
    use HasRoleBasedAccess;
}
