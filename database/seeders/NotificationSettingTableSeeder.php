<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationSettingTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'report_frequency', 'value' => 'daily'],    // daily | weekly | never
            ['key' => 'report_email', 'value' => 'admin@example.com'],
            ['key' => 'reminder_days_before', 'value' => '7'],
        ];

        foreach ($settings as $setting) {
            DB::table('notification_settings')->updateOrInsert(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
