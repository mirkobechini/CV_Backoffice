<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Group extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'invite_code',
        'public_status_token',
        'winter_switch_date',
        'summer_switch_date',
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
     * Licenze assegnate al gruppo nel tempo (una riga per periodo/rinnovo).
     */
    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    /**
     * L'ultima licenza assegnata (per id: le righe si creano solo in
     * ordine cronologico, mai retrodatate). hasOne...ofMany, non una
     * semplice query con limit(), per restare corretta anche quando questa
     * relazione viene eager-caricata su più gruppi insieme (un limit()
     * dentro un eager load si applicherebbe all'intero risultato, non per
     * gruppo).
     */
    public function latestLicense()
    {
        return $this->hasOne(License::class)->latestOfMany();
    }

    /**
     * La licenza più recente del gruppo (può essere scaduta: isExpired()
     * sulla licenza stessa distingue i due stati). Null = nessuna licenza
     * mai assegnata, trattato come illimitato per compatibilità con i
     * gruppi creati prima di questa feature.
     */
    public function currentLicense(): ?License
    {
        return $this->relationLoaded('latestLicense')
            ? $this->getRelation('latestLicense')
            : $this->latestLicense()->first();
    }

    /**
     * Nessuna licenza assegnata = illimitato (fail-open, compatibilità con
     * i gruppi creati prima di questa feature); con una licenza, il limite
     * si applica davvero (anche a 0 se scaduta: License::hasVehicleCapacity()
     * lo gestisce).
     */
    public function canAddVehicle(): bool
    {
        $license = $this->currentLicense();

        return ! $license || $license->hasVehicleCapacity($this->vehicles()->count());
    }

    public function canAddUser(): bool
    {
        $license = $this->currentLicense();

        return ! $license || $license->hasUserCapacity($this->users()->count());
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
     * Genera un token non indovinabile per la pagina pubblica di stato
     * flotta (più lungo dell'invite_code: qui la sicurezza dipende
     * interamente dall'essere impossibile da indovinare, non da un
     * secondo fattore come una password).
     */
    public static function generatePublicStatusToken(): string
    {
        do {
            $token = Str::random(40);
        } while (static::where('public_status_token', $token)->exists());

        return $token;
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
