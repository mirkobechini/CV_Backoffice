<?php

namespace Tests\Feature;

use App\Mail\EventNotificationMail;
use App\Models\Deadline;
use App\Models\Equipment;
use App\Models\EquipmentType;
use App\Models\Group;
use App\Models\Issue;
use App\Models\MaintenanceRecord;
use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Models\Provider;
use App\Models\Tire;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class GenerateNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole('admin')->create();
    }

    /**
     * Stesso gruppo creato dallo stato "admin" di UserFactory::withRole():
     * il veicolo deve appartenervi per essere considerato dal comando
     * (le sue query sono filtrate per gruppo).
     */
    private function defaultGroup(): Group
    {
        return Group::firstOrCreate(
            ['name' => 'Associazione di default'],
            ['invite_code' => Group::generateInviteCode()]
        );
    }

    private function vehicle(): Vehicle
    {
        return Vehicle::create([
            'license_plate' => 'AB123CD',
            'internal_code' => '0001',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => $this->defaultGroup()->id,
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

    public function test_generates_escalation_notification_for_overdue_unresolved_deadline(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->subDays(10),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 1);
        $this->assertStringContainsString('scaduta da', Notification::first()->title);
    }

    public function test_does_not_escalate_deadline_overdue_less_than_threshold(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->subDays(3),
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_escalation_notification_repeats_each_week_while_unresolved(): void
    {
        // Stessa settimana di ritardo: nessun duplicato (alreadyNotified()).
        $this->travelTo(Carbon::parse('2026-01-01'));
        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::parse('2025-12-22'), // 10 giorni di ritardo al 01/01
            'status' => Deadline::STATUS_EXPIRED,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications');
        $this->artisan('app:generate-notifications');
        $this->assertDatabaseCount('notifications', 1);

        // Settimana successiva di ritardo (17 giorni): nuovo bucket, nuova notifica.
        $this->travelTo(Carbon::parse('2026-01-08'));
        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 2);
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

    public function test_notification_message_does_not_contain_the_dedup_marker(): void
    {
        // Il marker (es. "#deadline-42") serve solo per alreadyNotified(),
        // ora in colonna propria — non deve più comparire nel testo
        // mostrato all'utente (bug segnalato nell'audit 2026-10-19).
        $this->admin();
        $vehicle = $this->vehicle();
        $deadline = Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        $this->artisan('app:generate-notifications');

        $notification = Notification::first();
        $this->assertStringNotContainsString('#', $notification->message);
        $this->assertSame("#deadline-{$deadline->id}", $notification->marker);
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

    public function test_generates_notification_for_expiring_collaudo(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        $type = EquipmentType::create([
            'name' => 'Estintore',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
        ]);
        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $type->id,
            'name' => 'Estintore 5kg',
            'next_collaudo_date' => Carbon::today()->addDays(3),
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'title' => 'Collaudo in scadenza: Estintore 5kg',
            'is_read' => false,
        ]);
    }

    public function test_does_not_duplicate_collaudo_notification(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        $type = EquipmentType::create([
            'name' => 'Estintore',
            'category' => EquipmentType::CATEGORY_FIRE_EXTINGUISHER,
        ]);
        Equipment::create([
            'vehicle_id' => $vehicle->id,
            'equipment_type_id' => $type->id,
            'name' => 'Estintore 5kg',
            'next_collaudo_date' => Carbon::today()->addDays(3),
        ]);

        $this->artisan('app:generate-notifications');
        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_generates_notification_for_upcoming_maintenance_appointment(): void
    {
        $this->admin();
        $vehicle = $this->vehicle();
        $provider = Provider::create(['name' => 'Officina Rossi', 'type' => 'Meccanico']);
        MaintenanceRecord::create([
            'vehicle_id' => $vehicle->id,
            'provider_id' => $provider->id,
            'appointment_date' => Carbon::today()->addDays(2),
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_MAINTENANCE,
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

    public function test_respects_per_user_notify_toggle(): void
    {
        // Un utente che ha disattivato le notifiche sui guasti non deve
        // riceverne, anche se il guasto esiste ed è aperto.
        $admin = $this->admin();
        NotificationSetting::create(['user_id' => $admin->id, 'key' => 'notify_on_issue', 'value' => '0']);

        $vehicle = $this->vehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Motore non parte',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_sends_email_when_email_option_and_recipient_configured(): void
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);
        NotificationSetting::create(['user_id' => $admin->id, 'key' => 'report_email', 'value' => 'admin@example.com']);

        Mail::fake();

        $this->artisan('app:generate-notifications --email');

        Mail::assertSent(EventNotificationMail::class, function ($mail) {
            return $mail->hasTo('admin@example.com');
        });
    }

    public function test_event_notification_email_renders_without_error_with_the_real_body_text(): void
    {
        // Mail::fake() (usato dal test sopra) non renderizza mai davvero la
        // vista: non avrebbe mai scoperto il bug reale in produzione, dove
        // la proprietà pubblica $message del Mailable veniva sovrascritta
        // da Laravel con l'istanza Illuminate\Mail\Message (riservata per
        // gli allegati inline), e {{ $message }} nella vista crashava con
        // "htmlspecialchars(): ... Message given" invece di mostrare il
        // testo della notifica. Qui si chiama davvero render(), come
        // succede quando il comando prova a inviare l'email sul serio.
        $mail = new EventNotificationMail('deadline', 'Titolo di prova', 'Corpo del messaggio di prova', 'https://example.com');

        $html = $mail->render();

        $this->assertStringContainsString('Corpo del messaggio di prova', $html);
    }

    public function test_email_failure_does_not_block_telegram_notifications(): void
    {
        // Bug reale: il ciclo email (senza try/catch) girava PRIMA di
        // quello Telegram nello stesso metodo — un'eccezione lì
        // interrompeva l'intero comando, impedendo per sempre al ciclo
        // Telegram sotto di eseguire, anche per destinatari senza alcun
        // problema di configurazione email.
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();
        Mail::shouldReceive('to->send')->andThrow(new \RuntimeException('SMTP non raggiungibile'));

        $admin = $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);
        NotificationSetting::create(['user_id' => $admin->id, 'key' => 'report_email', 'value' => 'admin@example.com']);
        NotificationSetting::create(['user_id' => $admin->id, 'key' => 'telegram_chat_id', 'value' => '999']);

        $this->artisan('app:generate-notifications --email');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'fake-token/sendMessage')
            && str_contains($request->body(), '999'));
    }

    public function test_does_not_send_email_without_email_option(): void
    {
        $admin = $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);
        NotificationSetting::create(['user_id' => $admin->id, 'key' => 'report_email', 'value' => 'admin@example.com']);

        Mail::fake();

        $this->artisan('app:generate-notifications');

        Mail::assertNothingSent();
    }

    public function test_generates_notification_for_vehicle_pending_seasonal_tire_change(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01'));

        $this->admin();
        $vehicle = $this->vehicle();
        Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_SUMMER,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_TIRE_SEASON,
            'is_read' => false,
        ]);
    }

    public function test_does_not_notify_vehicle_already_on_the_right_season(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01'));

        $this->admin();
        $vehicle = $this->vehicle();
        Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_WINTER,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_does_not_duplicate_tire_season_notification(): void
    {
        $this->travelTo(Carbon::parse('2026-12-01'));

        $this->admin();
        $vehicle = $this->vehicle();
        Tire::create([
            'vehicle_id' => $vehicle->id,
            'season' => Tire::SEASON_SUMMER,
            'position' => Tire::POSITION_FRONT_LEFT,
            'status' => Tire::STATUS_MOUNTED,
        ]);

        $this->artisan('app:generate-notifications');
        $this->artisan('app:generate-notifications');

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_capo_is_not_notified_about_another_groups_deadline(): void
    {
        // Prima del fix le query del comando non erano filtrate per gruppo:
        // un capo/sottocapo veniva notificato (e volendo anche mandato via
        // email) di scadenze/guasti/attrezzature/appuntamenti di QUALSIASI
        // gruppo, non solo del proprio.
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);
        $capoA = User::factory()->create();
        $groupA->addUser($capoA, Group::ROLE_CAPO);
        NotificationSetting::create(['user_id' => $capoA->id, 'key' => 'report_email', 'value' => 'capoA@example.com']);

        $vehicleB = Vehicle::create([
            'license_plate' => 'EF456GH',
            'internal_code' => '0002',
            'brand' => 'Fiat',
            'model' => 'Ducato',
            'immatricolation_date' => Carbon::today()->subYears(2),
            'group_id' => $groupB->id,
        ]);
        Deadline::create([
            'vehicle_id' => $vehicleB->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);

        Mail::fake();

        $this->artisan('app:generate-notifications --email');

        $this->assertDatabaseCount('notifications', 0);
        Mail::assertNothingSent();
    }
}
