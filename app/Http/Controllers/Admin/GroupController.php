<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\GroupInviteMail;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

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
     * Aggiorna il nome del gruppo (solo il capo).
     */
    public function update(Request $request, Group $group)
    {
        $this->authorizeGroup($group);

        // Solo il capo può rinominare il gruppo.
        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può rinominare il gruppo.');
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $group->update(['name' => $data['name']]);

        return back()->with('status', 'Gruppo aggiornato.');
    }

    /**
     * Aggiorna le date di cambio gomme stagionale del gruppo, usate dal
     * promemoria e dal riquadro in dashboard per QUESTA flotta.
     */
    public function updateTireSeason(Request $request, Group $group)
    {
        $this->authorizeGroup($group);

        // Solo il capo può cambiarle, come le altre impostazioni del gruppo.
        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può modificare le date di cambio gomme.');
        }

        $data = $request->validate([
            'winter_switch_month' => 'required|integer|min:1|max:12',
            'winter_switch_day' => 'required|integer|min:1|max:31',
            'summer_switch_month' => 'required|integer|min:1|max:12',
            'summer_switch_day' => 'required|integer|min:1|max:31',
        ]);

        // checkdate() verifica che il giorno esista per quel mese (usa un
        // anno bisestile fittizio per non respingere il 29 febbraio).
        if (! checkdate($data['winter_switch_month'], $data['winter_switch_day'], 2024)) {
            return back()->withErrors(['winter_switch_day' => 'Il giorno indicato non esiste per il mese scelto.'])->withInput();
        }
        if (! checkdate($data['summer_switch_month'], $data['summer_switch_day'], 2024)) {
            return back()->withErrors(['summer_switch_day' => 'Il giorno indicato non esiste per il mese scelto.'])->withInput();
        }

        $group->update([
            'winter_switch_date' => sprintf('%02d-%02d', $data['winter_switch_month'], $data['winter_switch_day']),
            'summer_switch_date' => sprintf('%02d-%02d', $data['summer_switch_month'], $data['summer_switch_day']),
        ]);

        return back()->with('status', 'Date di cambio gomme aggiornate con successo.');
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

        $user = $this->currentUser();

        // addUser()/syncWithoutDetaching() aggiorna il ruolo anche per un
        // utente già nel gruppo: senza questo controllo, un capo o
        // sottocapo che riusa un vecchio link di invito (es. un segnalibro)
        // veniva silenziosamente retrocesso a membro semplice.
        if ($user->roleIn($group)) {
            return redirect()->route('admin.groups.show', $group)->with('status', 'Fai già parte di questo gruppo.');
        }

        $group->addUser($user, Group::ROLE_MEMBER);

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
     * Invia un invito via email a un nuovo membro (solo il capo).
     * Il codice invito viene inviato all'email indicata.
     */
    public function invite(Request $request, Group $group)
    {
        $this->authorizeGroup($group);

        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può inviare inviti.');
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Se il gruppo non ha un codice invito, lo genera.
        if (! $group->invite_code) {
            $group->update(['invite_code' => Group::generateInviteCode()]);
        }

        Mail::to($data['email'])->send(new GroupInviteMail($group->name, $group->invite_code));

        return back()->with('status', "Invito inviato a {$data['email']}.");
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

        // Non si può retrocedere l'ultimo capo del gruppo.
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
     * I veicoli del gruppo vengono eliminati solo con conferma esplicita,
     * per evitare che restino orfani senza gruppo.
     */
    public function destroy(Request $request, Group $group)
    {
        $this->authorizeGroup($group);

        if ($this->currentUser()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può eliminare il gruppo.');
        }

        $deleteVehicles = $request->boolean('delete_vehicles');

        if ($group->vehicles()->exists() && ! $deleteVehicles) {
            return back()->withErrors([
                'delete_vehicles' => 'Il gruppo ha veicoli associati. Conferma di volerli eliminare per procedere.',
            ]);
        }

        if ($deleteVehicles) {
            $group->vehicles()->delete();
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
