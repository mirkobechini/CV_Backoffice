<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * I valori noti vengono automaticamente castati al tipo corretto.
     * Usa $this->attributes['key'] invece di $this->key per evitare
     * problemi di accesso durante l'idratazione del modello da Eloquent.
     */
    public function getValueAttribute($value)
    {
        $knownBooleans = [
            'notify_on_maintenance',
            'notify_on_deadline',
            'notify_on_issue',
            'notify_on_equipment',
        ];

        $key = $this->attributes['key'] ?? null;

        if ($key && in_array($key, $knownBooleans, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }
}
