<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Estrae i ~15 controlli di autorizzazione prima ripetuti manualmente in
 * GroupController (authorizeGroup() + abort(403) specifico per azione),
 * audit 2026-10-22, Q3. roleIn() ritorna null per chi non appartiene al
 * gruppo, quindi il confronto con ROLE_CAPO fallisce già da solo per un
 * non membro: non serve un controllo di appartenenza separato prima.
 */
class GroupPolicy
{
    public function view(User $user, Group $group): Response
    {
        return $user->roleIn($group) !== null
            ? Response::allow()
            : Response::deny('Non appartieni a questo gruppo.');
    }

    public function update(User $user, Group $group): Response
    {
        return $user->roleIn($group) === Group::ROLE_CAPO
            ? Response::allow()
            : Response::deny('Solo il capo può rinominare il gruppo.');
    }

    public function updateTireSeason(User $user, Group $group): Response
    {
        return $user->roleIn($group) === Group::ROLE_CAPO
            ? Response::allow()
            : Response::deny('Solo il capo può modificare le date di cambio gomme.');
    }

    public function updateInvitePermission(User $user, Group $group): Response
    {
        return $user->roleIn($group) === Group::ROLE_CAPO
            ? Response::allow()
            : Response::deny('Solo il capo può gestire questo permesso.');
    }

    public function manageInvites(User $user, Group $group): Response
    {
        return $group->userCanManageInvites($user)
            ? Response::allow()
            : Response::deny('Non puoi gestire il codice invito di questo gruppo.');
    }

    public function sendInvite(User $user, Group $group): Response
    {
        return $group->userCanManageInvites($user)
            ? Response::allow()
            : Response::deny('Non puoi inviare inviti per questo gruppo.');
    }

    public function managePublicStatus(User $user, Group $group): Response
    {
        return $user->roleIn($group) === Group::ROLE_CAPO
            ? Response::allow()
            : Response::deny('Solo il capo può gestire la pagina pubblica di stato.');
    }

    public function updateRole(User $user, Group $group): Response
    {
        return $user->roleIn($group) === Group::ROLE_CAPO
            ? Response::allow()
            : Response::deny('Solo il capo può gestire i ruoli.');
    }

    public function removeMember(User $user, Group $group): Response
    {
        return $user->roleIn($group) === Group::ROLE_CAPO
            ? Response::allow()
            : Response::deny('Solo il capo può rimuovere membri.');
    }
}
