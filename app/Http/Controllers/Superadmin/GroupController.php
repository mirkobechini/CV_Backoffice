<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuperadminGroupRequest;
use App\Models\Equipment;
use App\Models\FleetDevice;
use App\Models\FuelCard;
use App\Models\Group;
use App\Models\User;
use App\Services\AccountMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $capo = $group->capo();

        return view('superadmin.groups.edit', compact('group', 'capo'));
    }

    public function update(Request $request, Group $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $group->update(['name' => $data['name']]);

        return redirect()->route('superadmin.dashboard')->with('status', "Associazione rinominata in \"{$group->name}\".");
    }

    /**
     * Corregge nome/email del capo del gruppo. Serve soprattutto per un
     * refuso nell'email in fase di creazione: senza questo, il capo non
     * riceve mai il link per impostare la password e resta bloccato fuori
     * senza alcun modo di rimediare da solo (non può nemmeno loggarsi per
     * cambiarsi l'email dal proprio profilo).
     */
    public function updateCapo(Request $request, Group $group)
    {
        $capo = $group->capo();

        if (! $capo) {
            abort(404, 'Questo gruppo non ha ancora un capo.');
        }

        $data = $request->validate([
            'capo_name' => ['required', 'string', 'max:255'],
            'capo_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$capo->id],
        ]);

        $capo->name = $data['capo_name'];

        // Stesso comportamento di ProfileController::update(): un
        // indirizzo email diverso non è automaticamente verificato.
        if ($capo->email !== $data['capo_email']) {
            $capo->email = $data['capo_email'];
            $capo->email_verified_at = null;
        }

        $capo->save();

        return redirect()->route('superadmin.groups.edit', $group)->with('status', 'Dati del capo aggiornati.');
    }

    /**
     * Rimanda l'email per impostare la password al capo del gruppo (utile
     * se non l'ha mai ricevuta, es. dopo aver corretto un'email sbagliata).
     */
    public function resendCapoPasswordLink(Group $group, AccountMailer $accountMailer)
    {
        $capo = $group->capo();

        if (! $capo) {
            abort(404, 'Questo gruppo non ha ancora un capo.');
        }

        $sent = $accountMailer->sendPasswordSetupLink($capo);

        return redirect()->route('superadmin.groups.edit', $group)->with(
            'status',
            $sent
                ? "Email per impostare la password inviata a {$capo->email}."
                : "Invio dell'email a {$capo->email} fallito. Riprova più tardi."
        );
    }

    public function store(StoreSuperadminGroupRequest $request, AccountMailer $accountMailer)
    {
        $data = $request->validated();
        $newCapo = null;

        $group = DB::transaction(function () use ($data, $request, &$newCapo) {
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
                $newCapo = $capo;
            }

            return $group;
        });

        $passwordLinkSent = null;
        if ($newCapo) {
            $passwordLinkSent = $accountMailer->sendPasswordSetupLink($newCapo);
            // Inviata subito, non al primo tentativo di accesso bloccato
            // dal middleware 'verified': il capo la trova già pronta
            // insieme a quella per impostare la password.
            $accountMailer->sendEmailVerification($newCapo);
        }

        $capoStatus = match ($passwordLinkSent) {
            true => ' Email per impostare la password inviata al capo.',
            false => ' Invio dell\'email al capo fallito: usa "Invia di nuovo l\'email per impostare la password" dalla pagina del gruppo.',
            null => '',
        };

        return redirect()->route('superadmin.licenses.create', $group)
            ->with('status', "Associazione \"{$group->name}\" creata. Assegna ora una licenza.".$capoStatus);
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

        // FleetDevice/FuelCard/Equipment non hanno un ciclo di vita proprio
        // indipendente da gruppo/veicolo: senza questa pulizia restavano
        // orfani (puntando a un gruppo/veicolo cancellato) ma pienamente
        // vivi e interrogabili — audit sicurezza SM5. Equipment non ha un
        // group_id proprio (dedotto dal veicolo), quindi va ripulita solo
        // tramite i veicoli del gruppo.
        DB::transaction(function () use ($group, $deleteVehicles) {
            if ($deleteVehicles) {
                $vehicleIds = $group->vehicles()->pluck('id');

                Equipment::whereIn('vehicle_id', $vehicleIds)->delete();
                FleetDevice::where('group_id', $group->id)->orWhereIn('vehicle_id', $vehicleIds)->delete();
                FuelCard::where('group_id', $group->id)->orWhereIn('vehicle_id', $vehicleIds)->delete();

                $group->vehicles()->delete();
            } else {
                FleetDevice::where('group_id', $group->id)->delete();
                FuelCard::where('group_id', $group->id)->delete();
            }

            $group->delete();
        });

        return redirect()->route('superadmin.dashboard')->with('status', 'Gruppo eliminato.');
    }
}
