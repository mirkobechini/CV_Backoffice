<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Group;
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

    public function test_report_excludes_another_groups_vehicles_and_issues(): void
    {
        // Prima del fix il report era calcolato una sola volta su TUTTI i
        // veicoli/guasti/scadenze e inviato identico a ogni destinatario,
        // indipendentemente dal gruppo del destinatario stesso.
        Mail::fake();
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $userA = User::factory()->create();
        $groupA->addUser($userA, Group::ROLE_CAPO);
        NotificationSetting::create(['user_id' => $userA->id, 'key' => 'report_email', 'value' => 'capoA@example.com']);

        $vehicleA = $this->vehicle();
        $vehicleA->update(['group_id' => $groupA->id, 'internal_code' => 'A001']);
        $vehicleB = Vehicle::create([
            'license_plate' => 'ZZ999ZZ',
            'vehicle_type_id' => $vehicleA->vehicle_type_id,
            'internal_code' => 'B001',
            'brand_id' => $vehicleA->brand_id,
            'car_model_id' => $vehicleA->car_model_id,
            'fuel_type' => 'diesel',
            'immatricolation_date' => '2024-01-01',
            'group_id' => $groupB->id,
        ]);

        \App\Models\Issue::create(['vehicle_id' => $vehicleA->id, 'description' => 'Guasto gruppo A', 'status' => 'open', 'event_date' => '2025-01-01']);
        \App\Models\Issue::create(['vehicle_id' => $vehicleB->id, 'description' => 'Guasto gruppo B', 'status' => 'open', 'event_date' => '2025-01-01']);

        $this->artisan('app:send-summary-report');

        Mail::assertSent(ReportMail::class, function (ReportMail $mail) {
            $this->assertSame(1, $mail->data['totalVehicles']);
            $this->assertTrue($mail->data['openIssues']->contains('description', 'Guasto gruppo A'));
            $this->assertFalse($mail->data['openIssues']->contains('description', 'Guasto gruppo B'));

            return $mail->hasTo('capoA@example.com');
        });
    }

    public function test_report_shows_positive_days_remaining_for_upcoming_deadline(): void
    {
        // diffInDays() è firmato da Carbon 3 in poi: chiamarlo nell'ordine
        // sbagliato (due_date->diffInDays(now()) invece di
        // today->diffInDays(due_date)) dava "-5 giorni" per una scadenza
        // fra 5 giorni invece di "5 giorni".
        Mail::fake();
        $user = User::factory()->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_email', 'value' => 'admin@example.com']);
        $vehicle = $this->vehicle();
        \App\Models\Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => \App\Models\Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->addDays(5),
            'status' => \App\Models\Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->artisan('app:send-summary-report');

        Mail::assertSent(ReportMail::class, function (ReportMail $mail) {
            $html = preg_replace('/\s+/', ' ', $mail->render());
            $this->assertStringContainsString('Tra 5 giorni', $html);
            $this->assertStringNotContainsString('Tra -5 giorni', $html);

            return true;
        });
    }

    public function test_report_includes_deadline_expired_by_date_even_if_status_column_is_stale(): void
    {
        // Stesso bug già corretto sulla dashboard (v1.2.37): la colonna
        // status persistita viene risincronizzata solo alla creazione/
        // modifica della scadenza o visitando l'elenco scadenze, quindi una
        // scadenza il cui due_date passa senza che nessuno la tocchi resta
        // "pending" in DB anche se ormai scaduta.
        Mail::fake();
        $user = User::factory()->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'report_email', 'value' => 'admin@example.com']);
        $vehicle = $this->vehicle();
        \App\Models\Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => \App\Models\Deadline::TYPE_TAGLIANDO,
            'due_date' => now()->subDays(10),
            'status' => \App\Models\Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->artisan('app:send-summary-report');

        Mail::assertSent(ReportMail::class, function (ReportMail $mail) {
            return $mail->data['expiredDeadlines']->contains('type', \App\Models\Deadline::TYPE_TAGLIANDO);
        });
    }
}
