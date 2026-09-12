<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $backups = collect(Storage::disk('local')->files('backups'))
            ->map(fn ($file) => [
                'name' => basename($file),
                'size' => Storage::disk('local')->size($file),
                'modified' => Storage::disk('local')->lastModified($file),
            ])
            ->sortByDesc('modified')
            ->take(10);

        return view('admin.settings.index', compact('backups'));
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
