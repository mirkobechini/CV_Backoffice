<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    protected $fillable = [
        'group_id',
        'tier',
        'max_vehicles',
        'max_users',
        'features',
        'list_price_eur',
        'discount_code_id',
        'discount_percent',
        'final_price_eur',
        'starts_at',
        'expires_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'starts_at' => 'date',
            'expires_at' => 'date',
            'list_price_eur' => 'decimal:2',
            'final_price_eur' => 'decimal:2',
        ];
    }

    public const TIER_FREE = 'free';

    public const TIER_PRO = 'pro';

    public const TIER_ENTERPRISE = 'enterprise';

    public const TIERS = [self::TIER_FREE, self::TIER_PRO, self::TIER_ENTERPRISE];

    public const FEATURE_STATISTICS = 'statistics';

    public const FEATURE_AI_BACKFILL = 'ai_backfill';

    /**
     * Preset usati solo per precompilare il form di assegnazione: i valori
     * restano modificabili riga per riga, per accordi su misura senza
     * bisogno di nuovi tier.
     */
    public const TIER_PRESETS = [
        self::TIER_FREE => [
            'max_vehicles' => 3,
            'max_users' => 5,
            'features' => [],
            'list_price_eur' => 0,
        ],
        self::TIER_PRO => [
            'max_vehicles' => 15,
            'max_users' => 30,
            'features' => [self::FEATURE_STATISTICS, self::FEATURE_AI_BACKFILL],
            'list_price_eur' => 29,
        ],
        self::TIER_ENTERPRISE => [
            'max_vehicles' => null,
            'max_users' => null,
            'features' => [self::FEATURE_STATISTICS, self::FEATURE_AI_BACKFILL],
            'list_price_eur' => null,
        ],
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function discountCode()
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasFeature(string $key): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return in_array($key, $this->features ?? [], true);
    }

    /**
     * null su max_vehicles/max_users = illimitato: resta sempre vero.
     */
    public function hasVehicleCapacity(int $currentCount): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return $this->max_vehicles === null || $currentCount < $this->max_vehicles;
    }

    public function hasUserCapacity(int $currentCount): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return $this->max_users === null || $currentCount < $this->max_users;
    }

    /**
     * Prezzo finale dato un prezzo di listino e una percentuale di sconto
     * (0 se nessuno sconto applicato). Null su list_price_eur (es. tier
     * Enterprise su misura) resta null: non c'è un listino da scontare.
     */
    public static function computeFinalPrice(?float $listPriceEur, ?int $discountPercent): ?float
    {
        if ($listPriceEur === null) {
            return null;
        }

        $discountPercent ??= 0;

        return round($listPriceEur * (1 - $discountPercent / 100), 2);
    }
}
