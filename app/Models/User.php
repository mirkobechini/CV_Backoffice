<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Gruppi a cui l'utente appartiene (con ruolo nel pivot).
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Il gruppo attivo dell'utente (il primo a cui appartiene).
     */
    public function activeGroup(): ?Group
    {
        return $this->groups()->first();
    }

    /**
     * Impostazioni di notifica personali dell'utente (report email,
     * frequenza, promemoria, tipi di evento da notificare).
     */
    public function notificationSettings()
    {
        return $this->hasMany(NotificationSetting::class);
    }

    /**
     * Legge una singola impostazione di notifica dell'utente, con un
     * valore di default se non è mai stata impostata.
     */
    public function notificationSetting(string $key, mixed $default = null): mixed
    {
        $value = $this->notificationSettings()->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        $booleanKeys = ['notify_on_maintenance', 'notify_on_deadline', 'notify_on_issue', 'notify_on_equipment'];

        return in_array($key, $booleanKeys, true) ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : $value;
    }

    /**
     * Il ruolo dell'utente in un determinato gruppo.
     */
    public function roleIn(?Group $group): ?string
    {
        if (! $group) {
            return null;
        }

        $membership = $this->groups()->where('groups.id', $group->id)->first();

        return $membership?->pivot->role;
    }

    /**
     * True se l'utente è capo in almeno un gruppo.
     */
    public function isCapo(): bool
    {
        return $this->groups()->wherePivot('role', Group::ROLE_CAPO)->exists();
    }

    /**
     * True se l'utente è capo o sottocapo in almeno un gruppo.
     */
    public function isManager(): bool
    {
        return $this->groups()
            ->whereIn('group_user.role', [Group::ROLE_CAPO, Group::ROLE_SOTTOCAPO])
            ->exists();
    }

    /**
     * True se l'utente può gestire (modificare/creare/eliminare) i dati.
     * Capo e sottocapo possono gestire; i membri base solo visualizzare.
     */
    public function canManageData(): bool
    {
        return $this->isManager();
    }
}
