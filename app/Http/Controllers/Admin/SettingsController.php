<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Mostra la pagina impostazioni generali.
     */
    public function index()
    {
        $group = auth()->user()->activeGroup();

        $backups = collect(Storage::disk('local')->files('backups'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'size' => Storage::disk('local')->size($file),
                'modified' => Storage::disk('local')->lastModified($file),
            ])
            ->sortByDesc('modified')
            ->take(10);

        return view('admin.settings.index', compact('group', 'backups'));
    }

    /**
     * Aggiorna il nome del gruppo (associazione).
     */
    public function updateGroup(Request $request)
    {
        $group = auth()->user()->activeGroup();

        if (! $group) {
            abort(403, 'Non appartieni a nessun gruppo.');
        }

        // Solo il capo può modificare le impostazioni del gruppo.
        if (auth()->user()->roleIn($group) !== Group::ROLE_CAPO) {
            abort(403, 'Solo il capo può modificare le impostazioni.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $group->update(['name' => $data['name']]);

        return back()->with('status', 'Impostazioni aggiornate.');
    }

    /**
     * Crea un backup del database.
     */
    public function backup()
    {
        Artisan::call('app:backup-database');

        return back()->with('status', 'Backup creato con successo.');
    }
}
