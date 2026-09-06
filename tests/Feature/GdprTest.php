<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GdprTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_export_personal_data(): void
    {
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($user, Group::ROLE_CAPO);
        Notification::create(['user_id' => $user->id, 'type' => 'system', 'title' => 'Test']);

        $response = $this->actingAs($user)->get('/profile/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');

        $data = json_decode($response->getContent(), true);
        $this->assertEquals($user->email, $data['user']['email']);
        $this->assertCount(1, $data['groups']);
        $this->assertEquals('Gruppo A', $data['groups'][0]['name']);
        $this->assertCount(1, $data['notifications']);
    }

    public function test_capo_can_transfer_role_before_deleting_account(): void
    {
        $capo = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($member, Group::ROLE_MEMBER);

        $response = $this->actingAs($capo)->delete('/profile', [
            'password' => 'password',
            "successor_{$group->id}" => $member->id,
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($capo->fresh());

        // Il ruolo capo è stato trasferito al membro
        $this->assertEquals(Group::ROLE_CAPO, $member->roleIn($group->fresh()));
    }

    public function test_capo_can_delete_account_without_transfer(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $response = $this->actingAs($capo)->delete('/profile', [
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($capo->fresh());
    }
}
