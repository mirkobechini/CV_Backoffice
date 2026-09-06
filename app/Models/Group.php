<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Group extends Model
{
    protected $fillable = [
        'name',
        'invite_code',
    ];

    public const ROLE_CAPO = 'capo';

    public const ROLE_SOTTOCAPO = 'sottocapo';

    public const ROLE_MEMBER = 'member';

    /**
     * Utenti appartenenti al gruppo (con ruolo nel pivot).
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Veicoli appartenenti al gruppo.
     */
    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * Il capo del gruppo (primo utente con ruolo capo).
     */
    public function capo()
    {
        return $this->users()->wherePivot('role', self::ROLE_CAPO)->first();
    }

    /**
     * Genera un codice invito univoco.
     */
    public static function generateInviteCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    /**
     * Aggiunge un utente al gruppo con un determinato ruolo.
     */
    public function addUser(User $user, string $role = self::ROLE_MEMBER): void
    {
        $this->users()->syncWithoutDetaching([$user->id => ['role' => $role]]);
    }

    /**
     * Rimuove un utente dal gruppo.
     */
    public function removeUser(User $user): void
    {
        $this->users()->detach($user->id);
    }

    /**
     * Aggiorna il ruolo di un utente nel gruppo.
     */
    public function setUserRole(User $user, string $role): void
    {
        $this->users()->updateExistingPivot($user->id, ['role' => $role]);
    }
}
