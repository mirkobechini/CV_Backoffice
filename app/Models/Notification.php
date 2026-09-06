<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'url',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public const TYPE_DEADLINE = 'deadline';
    public const TYPE_ISSUE = 'issue';
    public const TYPE_EQUIPMENT = 'equipment';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_SYSTEM = 'system';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Notifiche non lette.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /**
     * Marca la notifica come letta.
     */
    public function markAsRead(): void
    {
        if ($this->is_read) {
            return;
        }
        $this->is_read = true;
        $this->read_at = now();
        $this->save();
    }

    /**
     * Icona Bootstrap per il tipo di notifica.
     */
    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEADLINE => '📅',
            self::TYPE_ISSUE => '⚠️',
            self::TYPE_EQUIPMENT => '🧯',
            self::TYPE_MAINTENANCE => '🔧',
            default => '🔔',
        };
    }
}
