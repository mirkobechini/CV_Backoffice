<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Mail\ReportMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendSummaryReportTest extends TestCase
{
    use RefreshDatabase;

    private function vehicle(): Vehicle
    {
        $vt = VehicleType::create(['name' => 'Ambulanza', 'needs_oxygen_check' => true, 'first_inspection_months' => 48, 'regular_inspection_months' => 24]);
        $brand = Brand::create(['name' => 'Fiat']);
        $model = CarModel::create(['name' => 'Ducato', 'brand_id' => $brand->id]);
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'vehicle_type_id' => $vt->id,
            'internal_code' => '1234',
            'brand_id' => $brand->id,
            'car_model_id' => $model->id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
        ]);
    }

    public function test_report_sends_email_when_recipient_configured(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_email', 'value' => 'admin@example.com']);

        $this->vehicle();

        $this->artisan('app:send-summary-report');

        Mail::assertSent(ReportMail::class);
    }

    public function test_report_warns_when_no_recipient(): void
    {
        Mail::fake();

        $this->vehicle();

        $this->artisan('app:send-summary-report');

        Mail::assertNotSent(ReportMail::class);
    }

    public function test_report_has_pdf_attachment(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_email', 'value' => 'admin@example.com']);

        $this->vehicle();

        $this->artisan('app:send-summary-report');

        Mail::assertSent(ReportMail::class, function (ReportMail $mail) {
            $attachments = $mail->attachments();
            $this->assertNotEmpty($attachments);
            $this->assertStringContainsString('.pdf', $attachments[0]->as);
            return true;
        });
    }

    public function test_report_sends_to_each_configured_user_independently(): void
    {
        Mail::fake();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        NotificationSetting::create(['user_id' => $userA->id, 'key' => 'report_email', 'value' => 'a@example.com']);
        NotificationSetting::create(['user_id' => $userB->id, 'key' => 'report_email', 'value' => 'b@example.com']);

        $this->vehicle();

        $this->artisan('app:send-summary-report');

        Mail::assertSent(ReportMail::class, fn ($mail) => $mail->hasTo('a@example.com'));
        Mail::assertSent(ReportMail::class, fn ($mail) => $mail->hasTo('b@example.com'));
    }

    public function test_report_skips_weekly_recipient_on_non_monday(): void
    {
        Mail::fake();
        $this->travelTo(now()->next(\Carbon\Carbon::TUESDAY));

        $user = User::factory()->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_email', 'value' => 'weekly@example.com']);
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_frequency', 'value' => 'weekly']);

        $this->vehicle();

        $this->artisan('app:send-summary-report');

        Mail::assertNotSent(ReportMail::class);
    }

    public function test_report_sends_to_weekly_recipient_on_monday(): void
    {
        Mail::fake();
        $this->travelTo(now()->next(\Carbon\Carbon::MONDAY));

        $user = User::factory()->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_email', 'value' => 'weekly@example.com']);
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_frequency', 'value' => 'weekly']);

        $this->vehicle();

        $this->artisan('app:send-summary-report');

        Mail::assertSent(ReportMail::class, fn ($mail) => $mail->hasTo('weekly@example.com'));
    }
}
