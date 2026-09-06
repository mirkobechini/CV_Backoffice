<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use App\Mail\EventNotificationMail;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GenerateNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(2),
        ]);
    }

    public function test_generates_notification_for_upcoming_deadline(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_DEADLINE,
            'is_read' => false,
        ]);
    }

    public function test_does_not_generate_duplicate_notifications(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications');
        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_generates_notification_for_open_issue(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Motore non parte',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_ISSUE,
            'is_read' => false,
        ]);
    }

    public function test_generates_notification_for_expiring_equipment(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        $type = EquipmentType::create(['name' => 'Estintore']);
        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $type->id,
            'name' => 'Estintore 5kg',
            'expiration_date' => Carbon::today()->addDays(3),
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_EQUIPMENT,
            'is_read' => false,
        ]);
    }

    public function test_ignores_renewed_deadlines(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_RENEWED,
            'is_renewed' => true,
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_sends_email_when_email_option_and_recipient_configured(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);
        DB::table('notification_settings')->insert([
            'key' => 'report_email',
            'value' => 'admin@example.com',
        ]);

        Mail::fake();

        $this->artisan('app:generate-notifications --email');

        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('admin@example.com');
        });
    }

    public function test_does_not_send_email_without_email_option(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);
        DB::table('notification_settings')->insert([
            'key' => 'report_email',
            'value' => 'admin@example.com',
        ]);

        Mail::fake();

        $this->artisan('app:generate-notifications');

        Mail::assertNothingSent();
    }
}
