<?php

namespace App\Policies;

use App\Policies\Concerns\HasGroupScopedAccess;

class IssuePolicy
{
    use HasGroupScopedAccess;
}
