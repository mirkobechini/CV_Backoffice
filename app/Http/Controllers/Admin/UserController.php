<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

/**
 * Creazione di un nuovo account utente, aggiunto direttamente a un gruppo.
 *
 * La gestione dei membri già esistenti (ruoli, rimozione) vive interamente
 * nella pagina del gruppo (GroupController); qui resta solo la creazione di
 * un account nuovo, alternativa all'invito via codice/email quando la
 * persona non può registrarsi da sola.
 */
class UserController extends Controller
{
    /**
     * Mostra il form per creare un utente e aggiungerlo al gruppo indicato.
     */
    public function create(Group $group)
    {
        $this->authorizeGroupCapo($group);

        return view('admin.users.create', compact('group'));
    }

    /**
     * Crea un nuovo utente e lo aggiunge al gruppo indicato.
     */
    public function store(Request $request, Group $group)
    {
        $this->authorizeGroupCapo($group);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:capo,sottocapo,member'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $group->addUser($user, $data['role']);

        return redirect()->route('admin.groups.show', $group)->with('status', 'Utente creato con successo.');
    }

    /**
     * L'utente deve appartenere al gruppo ed esserne il capo.
     */
    private function authorizeGroupCapo(Group $group): void
    {
        $currentUser = auth()->user();

        if (! $currentUser->groups()->where('groups.id', $group->id)->exists()) {
            abort(403, 'Non appartieni a questo gruppo.');
        }

        if ($currentUser->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può creare utenti.');
        }
    }
}
