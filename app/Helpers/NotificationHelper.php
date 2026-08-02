<?php

use App\Models\NotificationSetting;

if (!function_exists('notification_setting')) {
    function notification_setting(string $key, $default = null)
    {
        return NotificationSetting::where('key', $key)->value('value') ?? $default;
    }
}
