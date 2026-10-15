<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_capo_can_always_manage_invites(): void
    {
        $capo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);

        $this->assertTrue($group->userCanManageInvites($capo));
    }

    public function test_sottocapo_cannot_manage_invites_by_default(): void
    {
        $sottocapo = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($sottocapo, Group::ROLE_SOTTOCAPO);

        $this->assertFalse($group->userCanManageInvites($sottocapo));
    }

    public function test_sottocapo_can_manage_invites_when_enabled(): void
    {
        $sottocapo = User::factory()->create();
        $group = Group::create([
            'name' => 'Gruppo A',
            'invite_code' => 'AAAA1111',
            'sottocapo_can_manage_invites' => true,
        ]);
        $group->addUser($sottocapo, Group::ROLE_SOTTOCAPO);

        $this->assertTrue($group->userCanManageInvites($sottocapo));
    }

    public function test_member_cannot_manage_invites_even_when_enabled(): void
    {
        $member = User::factory()->create();
        $group = Group::create([
            'name' => 'Gruppo A',
            'invite_code' => 'AAAA1111',
            'sottocapo_can_manage_invites' => true,
        ]);
        $group->addUser($member, Group::ROLE_MEMBER);

        $this->assertFalse($group->userCanManageInvites($member));
    }

    public function test_only_capo_and_sottocapo_can_see_tire_season(): void
    {
        $capo = User::factory()->create();
        $sottocapo = User::factory()->create();
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $group->addUser($capo, Group::ROLE_CAPO);
        $group->addUser($sottocapo, Group::ROLE_SOTTOCAPO);
        $group->addUser($member, Group::ROLE_MEMBER);

        $this->assertTrue($group->userCanSeeTireSeason($capo));
        $this->assertTrue($group->userCanSeeTireSeason($sottocapo));
        $this->assertFalse($group->userCanSeeTireSeason($member));
    }
}
