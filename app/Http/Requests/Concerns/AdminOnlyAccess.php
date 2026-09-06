<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait AdminOnlyAccess
{
    public function authorize(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canManageData();
    }
}
