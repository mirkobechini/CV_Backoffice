<?php

namespace Tests\Unit;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGroupScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_permissions_follow_the_active_group_not_any_group(): void
    {
        // Audit sicurezza 2026-10-22 (SH1): un utente capo nel gruppo A che
        // si unisce anche al gruppo B come membro base non deve ereditare
        // i permessi di gestione dati mentre opera nel gruppo B.
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'AAAA1111']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'BBBB2222']);

        $user = User::factory()->create();
        $groupA->addUser($user, Group::ROLE_CAPO);
        $groupB->addUser($user, Group::ROLE_MEMBER);

        $this->assertTrue($user->activeGroup()->is($groupA));
        $this->assertTrue($user->isCapo());
        $this->assertTrue($user->isManager());
        $this->assertTrue($user->canManageData());
    }

    public function test_active_group_is_deterministically_the_first_joined(): void
    {
        $groupA = Group::create(['name' => 'Gruppo A', 'invite_code' => 'CCCC3333']);
        $groupB = Group::create(['name' => 'Gruppo B', 'invite_code' => 'DDDD4444']);

        $user = User::factory()->create();
        $groupA->addUser($user, Group::ROLE_MEMBER);
        $groupB->addUser($user, Group::ROLE_CAPO);

        // Entrato prima in A (come membro): A resta il gruppo attivo anche
        // se il ruolo "forte" è in B, e i permessi seguono A, non B.
        $this->assertTrue($user->activeGroup()->is($groupA));
        $this->assertFalse($user->isCapo());
        $this->assertFalse($user->canManageData());
    }
}
