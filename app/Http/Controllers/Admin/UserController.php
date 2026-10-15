<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

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
        $this->authorizeGroupInvites($group);

        return view('admin.users.create', compact('group'));
    }

    /**
     * Crea un nuovo utente e lo aggiunge al gruppo indicato.
     */
    public function store(Request $request, Group $group)
    {
        $this->authorizeGroupInvites($group);

        if (! $group->canAddUser()) {
            return back()->withErrors([
                'license' => 'Hai raggiunto il limite di utenti della tua licenza. Contatta l\'amministratore per aumentarlo.',
            ])->withInput();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:capo,sottocapo,member'],
        ]);

        // Un sottocapo con permesso di gestire inviti non può comunque
        // creare un nuovo capo: solo il capo stesso può farlo.
        if ($data['role'] === Group::ROLE_CAPO && auth()->user()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può creare un altro capo.');
        }

        // Password casuale e inutilizzabile: chi crea l'account non deve
        // conoscerla. Il nuovo utente la imposta da solo dal link che
        // riceve via email (stessa notifica del "password dimenticata",
        // vedi AppServiceProvider::boot()).
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make(Str::random(40)),
        ]);

        $group->addUser($user, $data['role']);

        Password::sendResetLink(['email' => $user->email]);
        // Inviata subito, non al primo tentativo di accesso bloccato dal
        // middleware 'verified': il nuovo utente la trova già pronta
        // insieme a quella per impostare la password.
        $user->sendEmailVerificationNotification();

        return redirect()->route('admin.groups.show', $group)->with('status', 'Utente creato con successo. Gli è stata inviata un\'email per impostare la password.');
    }

    /**
     * L'utente deve appartenere al gruppo e poter gestire gli inviti
     * (capo, o sottocapo se il capo lo ha abilitato).
     */
    private function authorizeGroupInvites(Group $group): void
    {
        $currentUser = auth()->user();

        if (! $currentUser->groups()->where('groups.id', $group->id)->exists()) {
            abort(403, 'Non appartieni a questo gruppo.');
        }

        if (! $group->userCanManageInvites($currentUser)) {
            abort(403, 'Non puoi creare utenti in questo gruppo.');
        }
    }
}
