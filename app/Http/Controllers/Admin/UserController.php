<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * Elenco degli utenti del gruppo dell'utente corrente.
     */
    public function index()
    {
        $group = auth()->user()->activeGroup();

        if (! $group) {
            return view('admin.users.index', ['users' => collect()]);
        }

        $users = $group->users()->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'group'));
    }

    /**
     * Mostra il form per creare un utente.
     */
    public function create()
    {
        return view('admin.users.create');
    }

    /**
     * Crea un nuovo utente e lo aggiunge al gruppo dell'utente corrente.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'in:capo,sottocapo,member'],
        ]);

        $group = auth()->user()->activeGroup();

        if (! $group) {
            abort(403, 'Non appartieni a nessun gruppo.');
        }

        // Solo il capo può creare utenti.
        if (auth()->user()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può creare utenti.');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $group->addUser($user, $data['role']);

        return redirect()->route('admin.users.index')->with('status', 'Utente creato con successo.');
    }

    /**
     * Aggiorna il ruolo di un utente nel gruppo.
     */
    public function updateRole(Request $request, User $user)
    {
        $group = auth()->user()->activeGroup();

        if (! $group) {
            abort(403, 'Non appartieni a nessun gruppo.');
        }

        // Solo il capo può gestire i ruoli.
        if (auth()->user()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può gestire i ruoli.');
        }

        // L'utente target deve appartenere al gruppo.
        if (! $user->roleIn($group)) {
            abort(404, 'Utente non trovato in questo gruppo.');
        }

        $data = $request->validate([
            'role' => ['required', 'in:capo,sottocapo,member'],
        ]);

        // Non si può retrocedere l'ultimo capo.
        if ($user->roleIn($group) === Group::ROLE_CAPO && $data['role'] !== Group::ROLE_CAPO) {
            $capoCount = $group->users()->wherePivot('role', Group::ROLE_CAPO)->count();
            if ($capoCount <= 1) {
                abort(403, 'Non puoi retrocedere l\'ultimo capo del gruppo.');
            }
        }

        $group->setUserRole($user, $data['role']);

        return back()->with('status', 'Ruolo aggiornato.');
    }

    /**
     * Rimuove un utente dal gruppo.
     */
    public function destroy(User $user)
    {
        $group = auth()->user()->activeGroup();

        if (! $group) {
            abort(403, 'Non appartieni a nessun gruppo.');
        }

        // Solo il capo può rimuovere utenti.
        if (auth()->user()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può rimuovere utenti.');
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

        return back()->with('status', 'Utente rimosso dal gruppo.');
    }
}
