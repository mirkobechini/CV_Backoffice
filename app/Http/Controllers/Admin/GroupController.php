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
    public function show(Request $request, Group $group)
    {
        $this->authorize('view', $group);

        $group->load(['users', 'vehicles']);

        $user = $this->currentUser();
        $canSeeSettings = $group->userCanManageInvites($user) || $group->userCanSeeTireSeason($user);

        $tab = $request->query('tab', 'panoramica');
        if (! in_array($tab, ['panoramica', 'impostazioni'], true) || ($tab === 'impostazioni' && ! $canSeeSettings)) {
            $tab = 'panoramica';
        }

        return view('admin.groups.show', compact('group', 'tab', 'canSeeSettings'));
    }

    /**
     * Aggiorna il nome del gruppo (solo il capo).
     */
    public function update(Request $request, Group $group)
    {
        $this->authorize('update', $group);

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
        $this->authorize('updateTireSeason', $group);

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
     * Attiva/disattiva la possibilità per i sottocapi di gestire gli
     * inviti (codice, email, creazione account diretta). Solo il capo.
     */
    public function updateInvitePermission(Request $request, Group $group)
    {
        $this->authorize('updateInvitePermission', $group);

        $group->update(['sottocapo_can_manage_invites' => $request->boolean('sottocapo_can_manage_invites')]);

        return back()->with('status', 'Permesso aggiornato.');
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

        if (! $group->canAddUser()) {
            return back()->withErrors([
                'invite_code' => 'Questo gruppo ha raggiunto il limite di utenti della sua licenza.',
            ]);
        }

        $group->addUser($user, Group::ROLE_MEMBER);

        return redirect()->route('admin.groups.show', $group)->with('status', 'Sei entrato nel gruppo.');
    }

    /**
     * Rigenera il codice invito del gruppo (solo il capo).
     */
    public function regenerateInviteCode(Group $group)
    {
        $this->authorize('manageInvites', $group);

        $group->update(['invite_code' => Group::generateInviteCode()]);

        return back()->with('status', 'Codice invito rigenerato.');
    }

    /**
     * Genera (o rigenera, invalidando il link precedente) il token della
     * pagina pubblica di stato flotta del gruppo (solo il capo).
     */
    public function generatePublicStatusToken(Group $group)
    {
        $this->authorize('managePublicStatus', $group);

        $group->update(['public_status_token' => Group::generatePublicStatusToken()]);

        return back()->with('status', 'Link della pagina di stato generato.');
    }

    /**
     * Disattiva la pagina pubblica di stato flotta del gruppo (solo il capo).
     */
    public function revokePublicStatusToken(Group $group)
    {
        $this->authorize('managePublicStatus', $group);

        $group->update(['public_status_token' => null]);

        return back()->with('status', 'Pagina di stato disattivata.');
    }

    /**
     * Invia un invito via email a un nuovo membro (solo il capo).
     * Il codice invito viene inviato all'email indicata.
     */
    public function invite(Request $request, Group $group)
    {
        $this->authorize('sendInvite', $group);

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
        $this->authorize('updateRole', $group);

        $data = $request->validate([
            'role' => 'required|in:capo,sottocapo,member',
        ]);

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
        $this->authorize('removeMember', $group);

        // L'utente target deve appartenere al gruppo.
        if (! $user->roleIn($group)) {
            abort(404, 'Utente non trovato in questo gruppo.');
        }

        // Non si può rimuovere il capo.
        if ($user->roleIn($group) === Group::ROLE_CAPO) {
            abort(403, 'Non puoi rimuovere il capo del gruppo.');
        }

        // Non si può lasciare un membro senza nessun gruppo: tutta
        // l'autorizzazione dell'app (query scoping + policy) assume che un
        // utente reale appartenga sempre ad almeno un gruppo. Un utente
        // senza gruppo attivo non deve restare loggato in questo stato
        // (vedi audit sicurezza 2026-10-09).
        if ($user->groups()->count() <= 1) {
            abort(403, 'Non puoi rimuovere questo membro: è il suo unico gruppo. Eliminane l\'account invece, se necessario.');
        }

        $group->removeUser($user);

        return back()->with('status', 'Membro rimosso dal gruppo.');
    }
}
