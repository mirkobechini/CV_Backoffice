<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Facades\Auth;

trait AdminOnlyAccess
{
    public function authorize(): bool
    {
        return Auth::user()?->role === 'admin';
    }
}