<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * I valori noti vengono automaticamente castati al tipo corretto.
     */
    public function getValueAttribute($value)
    {
        $knownBooleans = [
            'notify_on_maintenance',
            'notify_on_deadline',
            'notify_on_issue',
            'notify_on_equipment',
        ];

        if (in_array($this->key, $knownBooleans, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return $value;
    }
}
