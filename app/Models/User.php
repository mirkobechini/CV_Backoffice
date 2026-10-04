<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
        'two_factor_secret',
        'two_factor_recovery_codes',
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
            'two_factor_confirmed_at' => 'datetime',
            // Cifrati: il segreto TOTP e i codici di recupero sono
            // equivalenti a credenziali, non devono essere leggibili in
            // chiaro da un dump del database (vedi anche VerifyBackup).
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * True se l'utente ha confermato l'attivazione del 2FA (un segreto
     * generato ma non ancora confermato con un codice non conta).
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Genera un nuovo set di codici di recupero (8, uso singolo ciascuno),
     * sostituendo quelli esistenti. Usata sia alla prima conferma del 2FA
     * sia per una rigenerazione manuale.
     *
     * @return array<int, string> i codici in chiaro, da mostrare una sola
     *                            volta all'utente
     */
    public function generateRecoveryCodes(): array
    {
        $codes = Collection::times(8, fn () => Str::random(10) . '-' . Str::random(10))->all();

        $this->two_factor_recovery_codes = $codes;
        $this->save();

        return $codes;
    }

    /**
     * Verifica e consuma un codice di recupero (uso singolo): se valido,
     * lo rimuove dalla lista e salva, per non poterlo riusare.
     */
    public function redeemRecoveryCode(string $code): bool
    {
        $codes = $this->two_factor_recovery_codes ?? [];
        $index = array_search($code, $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $this->two_factor_recovery_codes = array_values($codes);
        $this->save();

        return true;
    }

    /**
     * Disattiva il 2FA e rimuove tutti i dati collegati.
     */
    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
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
     *
     * Usa la relazione (proprietà, non metodo) invece di una query diretta:
     * se il chiamante ha già fatto with('notificationSettings') su più
     * utenti (es. un comando schedulato che itera su tutti gli admin), le
     * chiamate successive per lo stesso utente non generano query
     * aggiuntive — altrimenti la relazione viene caricata una volta sola e
     * riusata per le chiamate successive con chiavi diverse sullo stesso
     * utente. Il cast booleano è già gestito da
     * NotificationSetting::getValueAttribute().
     */
    public function notificationSetting(string $key, mixed $default = null): mixed
    {
        $setting = $this->notificationSettings->firstWhere('key', $key);

        return $setting?->value ?? $default;
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
