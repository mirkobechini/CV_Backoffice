<?php

namespace Tests\Feature;

use App\Models\NotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_setting_returns_value(): void
    {
        NotificationSetting::create(['key' => 'report_email', 'value' => 'admin@example.com']);

        $this->assertEquals('admin@example.com', notification_setting('report_email'));
    }

    public function test_notification_setting_returns_default_when_missing(): void
    {
        $this->assertEquals('default@example.com', notification_setting('report_email', 'default@example.com'));
    }

    public function test_notification_setting_returns_null_when_missing_and_no_default(): void
    {
        $this->assertNull(notification_setting('report_email'));
    }
}
