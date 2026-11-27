<?php

namespace Tests\Feature;

use App\Models\Deadline;
use App\Models\Group;
use App\Models\Issue;
use App\Models\Notification;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Il digest Telegram (un solo messaggio per utente per giro, invece di uno
 * separato per evento) e le icone per tipo, estratti da
 * GenerateNotificationsCommandTest.php (già a 496 righe) per non superare la
 * soglia dimensionale. Segnalato da Mirko il 2026-11-26: una raffica di
 * notifiche push separate e senza icone distintive per tipo era difficile da
 * seguire.
 */
class GenerateNotificationsTelegramDigestTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->withRole('admin')->create();
        NotificationSetting::create(['user_id' => $user->id, 'key' => 'telegram_chat_id', 'value' => '999']);

        return $user;
    }

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

    public function test_sends_a_single_telegram_message_combining_multiple_events(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $this->admin();
        $vehicle = $this->vehicle();
        Deadline::create([
            'vehicle_id' => $vehicle->id,
            'type' => Deadline::TYPE_TAGLIANDO,
            'due_date' => Carbon::today()->addDays(5),
            'status' => Deadline::STATUS_PENDING,
            'is_renewed' => false,
        ]);
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Motore non parte',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications --email');

        // Un solo messaggio Telegram per questo run, non due separati —
        // prima della fix, scadenza e guasto arrivavano come due notifiche
        // push distinte invece di un unico riepilogo.
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $body = $request->body();

            return str_contains($request->url(), 'fake-token/sendMessage')
                && str_contains($body, 'Motore non parte')
                && str_contains($body, Deadline::TYPE_TAGLIANDO);
        });
    }

    public function test_telegram_message_prefixes_each_event_with_its_type_icon(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $this->admin();
        $vehicle = $this->vehicle();
        Issue::create([
            'vehicle_id' => $vehicle->id,
            'description' => 'Motore non parte',
            'status' => 'open',
        ]);

        $this->artisan('app:generate-notifications --email');

        Http::assertSent(fn ($request) => str_contains($request['text'] ?? '', Notification::iconForType(Notification::TYPE_ISSUE)));
    }

    public function test_splits_into_multiple_messages_when_the_digest_would_exceed_telegrams_limit(): void
    {
        config(['services.telegram.bot_token' => 'fake-token']);
        Http::fake();

        $this->admin();
        $vehicle = $this->vehicle();
        // Una descrizione lunga per evento rende facile superare 4096
        // caratteri con poche decine di guasti, senza dover creare
        // centinaia di record solo per il test. Non verifichiamo un numero
        // esatto di messaggi (dipenderebbe da calcoli di lunghezza fragili
        // da replicare qui), solo che lo split sia avvenuto davvero: più di
        // un messaggio, meno di uno per evento.
        $longDescription = str_repeat('Guasto molto dettagliato. ', 20);
        for ($i = 0; $i < 20; $i++) {
            Issue::create([
                'vehicle_id' => $vehicle->id,
                'description' => $longDescription.$i,
                'status' => 'open',
            ]);
        }

        $this->artisan('app:generate-notifications --email');

        $sentCount = Http::recorded()->count();
        $this->assertGreaterThan(1, $sentCount, 'Il digest avrebbe dovuto essere spezzato in più messaggi.');
        $this->assertLessThan(20, $sentCount, 'Il digest non stava raggruppando affatto gli eventi.');
    }
}
