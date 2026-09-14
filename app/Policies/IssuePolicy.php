<?php

namespace App\Policies;

use App\Models\Issue;
use App\Policies\Concerns\HasGroupScopedAccess;

class IssuePolicy
{
    use HasGroupScopedAccess;
}
