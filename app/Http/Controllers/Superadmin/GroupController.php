<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuperadminGroupRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    /**
     * Form di onboarding di una nuova associazione. Creare un gruppo (e,
     * salvo l'opzione "sono io il capo", anche l'account del suo primo
     * capo) è un'azione riservata al superadmin: è l'unico punto
     * d'ingresso rimasto per i nuovi gruppi, altrimenti il sistema di
     * licenze sarebbe bypassabile da chiunque abbia un account (vedi ADR
     * §16/§17).
     */
    public function create()
    {
        return view('superadmin.groups.create');
    }

    public function edit(Group $group)
    {
        return view('superadmin.groups.edit', compact('group'));
    }

    public function update(Request $request, Group $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $group->update(['name' => $data['name']]);

        return redirect()->route('superadmin.dashboard')->with('status', "Associazione rinominata in \"{$group->name}\".");
    }

    public function store(StoreSuperadminGroupRequest $request)
    {
        $data = $request->validated();
        $newCapoEmail = null;

        $group = DB::transaction(function () use ($data, $request, &$newCapoEmail) {
            $group = Group::create([
                'name' => $data['name'],
                'invite_code' => Group::generateInviteCode(),
            ]);

            if ($request->boolean('self_capo')) {
                $group->addUser($request->user(), Group::ROLE_CAPO);
            } else {
                // Password casuale e inutilizzabile: il superadmin non deve
                // conoscerla. Il capo la imposta lui stesso dal link che
                // riceve via email (stessa notifica del "password
                // dimenticata", vedi AppServiceProvider::boot()).
                $capo = User::create([
                    'name' => $data['capo_name'],
                    'email' => $data['capo_email'],
                    'password' => Hash::make(Str::random(40)),
                ]);

                $group->addUser($capo, Group::ROLE_CAPO);
                $newCapoEmail = $capo->email;
            }

            return $group;
        });

        if ($newCapoEmail) {
            Password::sendResetLink(['email' => $newCapoEmail]);
        }

        return redirect()->route('superadmin.licenses.create', $group)
            ->with('status', "Associazione \"{$group->name}\" creata. Assegna ora una licenza.".
                ($newCapoEmail ? ' Email per impostare la password inviata al capo.' : ''));
    }

    /**
     * Elimina (soft delete) un gruppo. Stessa conferma a scrittura del
     * nome già usata quando questa azione viveva in /admin (ADR §13/§16):
     * ora è centralizzata qui, un capo non può più eliminare il proprio
     * gruppo da solo.
     */
    public function destroy(Request $request, Group $group)
    {
        $request->validate(['confirm_name' => 'required|string']);

        if (trim($request->input('confirm_name')) !== $group->name) {
            return back()->withErrors([
                'confirm_name' => 'Il nome digitato non corrisponde al nome del gruppo.',
            ]);
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

        return redirect()->route('superadmin.dashboard')->with('status', 'Gruppo eliminato.');
    }
}
