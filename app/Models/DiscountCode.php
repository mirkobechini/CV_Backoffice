<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DiscountCode extends Model
{
    protected $fillable = [
        'code',
        'percent',
        'max_uses',
        'expires_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'active' => 'boolean',
        ];
    }

    public function licenses()
    {
        return $this->hasMany(License::class);
    }

    /**
     * Genera un codice univoco, stesso pattern di Group::generateInviteCode().
     */
    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    /**
     * True se il codice è ancora utilizzabile (attivo, non scaduto, non
     * esaurito). Non consuma un utilizzo: solo verifica.
     */
    public function isUsable(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    /**
     * Consuma un utilizzo del codice, se ancora valido.
     */
    public function redeem(): bool
    {
        if (! $this->isUsable()) {
            return false;
        }

        $this->increment('used_count');

        return true;
    }
}
