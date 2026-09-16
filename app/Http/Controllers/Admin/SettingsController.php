<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FleetSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Mostra la pagina impostazioni generali.
     *
     * Le impostazioni del gruppo (nome, codice invito, membri) si gestiscono
     * solo dalla pagina del gruppo stesso; qui restano solo le azioni di
     * sistema che non sono né personali né di un gruppo specifico.
     */
    public function index()
    {
        $canManageBackups = auth()->user()->canManageData();

        // L'elenco dei backup è un dettaglio della sezione backup, visibile
        // solo a chi può effettivamente gestirli.
        $backups = $canManageBackups
            ? collect(Storage::disk('local')->files('backups'))
                ->map(fn ($file) => [
                    'name' => basename($file),
                    'size' => Storage::disk('local')->size($file),
                    'modified' => Storage::disk('local')->lastModified($file),
                ])
                ->sortByDesc('modified')
                ->take(10)
            : collect();

        $fleetSettings = FleetSetting::current();

        return view('admin.settings.index', compact('backups', 'canManageBackups', 'fleetSettings'));
    }

    /**
     * Aggiorna le date globali (uguali per tutta la flotta) di cambio
     * gomme stagionale, usate per il promemoria e il widget in dashboard.
     */
    public function updateTireSeason(Request $request)
    {
        if (! auth()->user()->canManageData()) {
            abort(403);
        }

        $data = $request->validate([
            'winter_switch_month' => 'required|integer|min:1|max:12',
            'winter_switch_day' => 'required|integer|min:1|max:31',
            'summer_switch_month' => 'required|integer|min:1|max:12',
            'summer_switch_day' => 'required|integer|min:1|max:31',
        ], [], [
            'winter_switch_day' => 'giorno di cambio invernale',
            'summer_switch_day' => 'giorno di cambio estivo',
        ]);

        // checkdate() verifica che il giorno esista per quel mese (usa un
        // anno bisestile fittizio per non respingere il 29 febbraio).
        if (! checkdate($data['winter_switch_month'], $data['winter_switch_day'], 2024)) {
            return back()->withErrors(['winter_switch_day' => 'Il giorno indicato non esiste per il mese scelto.'])->withInput();
        }
        if (! checkdate($data['summer_switch_month'], $data['summer_switch_day'], 2024)) {
            return back()->withErrors(['summer_switch_day' => 'Il giorno indicato non esiste per il mese scelto.'])->withInput();
        }

        FleetSetting::current()->update([
            'winter_switch_date' => sprintf('%02d-%02d', $data['winter_switch_month'], $data['winter_switch_day']),
            'summer_switch_date' => sprintf('%02d-%02d', $data['summer_switch_month'], $data['summer_switch_day']),
        ]);

        return back()->with('status', 'Date di cambio gomme aggiornate con successo.');
    }

    /**
     * Crea un backup del database.
     *
     * Azione di sistema riservata a capo/sottocapo: prima non aveva alcun
     * controllo di autorizzazione, quindi qualunque utente autenticato
     * (anche un membro base) poteva lanciarla direttamente sulla route.
     */
    public function backup()
    {
        if (! auth()->user()->canManageData()) {
            abort(403);
        }

        Artisan::call('app:backup-database');

        return back()->with('status', 'Backup creato con successo.');
    }
}
