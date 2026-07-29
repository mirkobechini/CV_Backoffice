<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait HasRoleBasedAccess
{
    /**
     * Solo admin può fare qualsiasi operazione.
     * Gli altri (worker, volounteer, manager) possono solo vedere.
     */
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null; // lascia decidere ai metodi specifici
    }

    public function viewAny(User $user): bool
    {
        return true; // tutti possono vedere le liste
    }

    public function view(User $user, mixed $model): bool
    {
        return true; // tutti possono vedere i dettagli
    }

    public function create(User $user): bool
    {
        return false; // solo admin (gestito da before)
    }

    public function update(User $user, mixed $model): bool
    {
        return false; // solo admin (gestito da before)
    }

    public function delete(User $user, mixed $model): bool
    {
        return false; // solo admin (gestito da before)
    }
}
