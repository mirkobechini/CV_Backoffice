<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GroupController extends Controller
{
    /**
     * L'utente autenticato come modello User.
     */
    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * Elenco dei gruppi dell'utente corrente.
     */
    public function index()
    {
        $groups = $this->currentUser()->groups()->withCount('users', 'vehicles')->get();

        return view('admin.groups.index', compact('groups'));
    }

    /**
     * Dettaglio gruppo: membri, ruoli, codice invito.
     */
    public function show(Group $group)
    {
        $this->authorizeGroup($group);

        $group->load(['users', 'vehicles']);

        return view('admin.groups.show', compact('group'));
    }

    /**
     * Mostra il form per creare un gruppo.
     */
    public function create()
    {
        return view('admin.groups.create');
    }

    /**
     * Crea un nuovo gruppo e assegna l'utente corrente come capo.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $group = Group::create([
            'name' => $data['name'],
            'invite_code' => Group::generateInviteCode(),
        ]);

        $group->addUser($this->currentUser(), Group::ROLE_CAPO);

        return redirect()->route('admin.groups.show', $group)->with('status', 'Gruppo creato con successo.');
    }

    /**
     * Aggiorna il nome del gruppo.
     */
    public function update(Request $request, Group $group)
    {
        $this->authorizeGroup($group);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $group->update(['name' => $data['name']]);

        return back()->with('status', 'Gruppo aggiornato.');
    }

    /**
     * Aggiunge un utente al gruppo tramite codice invito.
     */
    public function join(Request $request)
    {
        $data = $request->validate([
            'invite_code' => 'required|string|max:12',
        ]);

        $group = Group::where('invite_code', strtoupper($data['invite_code']))->first();

        if (! $group) {
            return back()->withErrors(['invite_code' => 'Codice invito non valido.']);
        }

        $group->addUser($this->currentUser(), Group::ROLE_MEMBER);

        return redirect()->route('admin.groups.show', $group)->with('status', 'Sei entrato nel gruppo.');
    }

    /**
     * Rigenera il codice invito del gruppo (solo il capo).
     */
    public function regenerateInviteCode(Group $group)
    {
        $this->authorizeGroup($group);

        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può rigenerare il codice invito.');
        }

        $group->update(['invite_code' => Group::generateInviteCode()]);

        return back()->with('status', 'Codice invito rigenerato.');
    }

    /**
     * Aggiorna il ruolo di un membro del gruppo.
     */
    public function updateRole(Request $request, Group $group, User $user)
    {
        $this->authorizeGroup($group);

        $data = $request->validate([
            'role' => 'required|in:capo,sottocapo,member',
        ]);

        // Solo il capo può gestire i ruoli.
        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può gestire i ruoli.');
        }

        // L'utente target deve appartenere al gruppo.
        if (! $user->roleIn($group)) {
            abort(404, 'Utente non trovato in questo gruppo.');
        }

        $group->setUserRole($user, $data['role']);

        return back()->with('status', 'Ruolo aggiornato.');
    }

    /**
     * Rimuove un membro dal gruppo.
     */
    public function removeMember(Group $group, User $user)
    {
        $this->authorizeGroup($group);

        // Solo il capo può rimuovere membri.
        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può rimuovere membri.');
        }

        // L'utente target deve appartenere al gruppo.
        if (! $user->roleIn($group)) {
            abort(404, 'Utente non trovato in questo gruppo.');
        }

        // Non si può rimuovere il capo.
        if ($user->roleIn($group) === Group::ROLE_CAPO) {
            abort(403, 'Non puoi rimuovere il capo del gruppo.');
        }

        $group->removeUser($user);

        return back()->with('status', 'Membro rimosso dal gruppo.');
    }

    /**
     * Elimina un gruppo (solo il capo).
     */
    public function destroy(Group $group)
    {
        $this->authorizeGroup($group);

        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può eliminare il gruppo.');
        }

        $group->delete();

        return redirect()->route('admin.groups.index')->with('status', 'Gruppo eliminato.');
    }

    /**
     * Verifica che l'utente appartenga al gruppo.
     */
    private function authorizeGroup(Group $group): void
    {
        if (! $this->currentUser()->groups()->where('groups.id', $group->id)->exists()) {
            abort(403, 'Non appartieni a questo gruppo.');
        }
    }
}
