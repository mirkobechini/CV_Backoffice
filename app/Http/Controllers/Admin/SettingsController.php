<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Mostra la pagina impostazioni generali.
     *
     * Le impostazioni del gruppo (nome, codice invito, membri, date di
     * cambio gomme stagionale) si gestiscono solo dalla pagina del gruppo
     * stesso; qui restano solo le azioni di sistema che non sono né
     * personali né di un gruppo specifico.
     */
    public function index()
    {
        // Riservato al superadmin (audit sicurezza 2026-10-22): il backup
        // creato da app:backup-database è un unico dump dell'INTERO
        // database multi-tenant, non del solo gruppo di chi lo richiede —
        // prima bastava essere capo/sottocapo di una QUALSIASI associazione
        // per vedere ed eseguire i backup di tutte le altre.
        $canManageBackups = auth()->user()->isSuperadmin();

        // L'elenco dei backup è un dettaglio della sezione backup, visibile
        // solo a chi può effettivamente gestirli.
        $backupsDisk = Storage::disk(config('filesystems.uploads_disk'));

        $backups = $canManageBackups
            ? collect($backupsDisk->files('backups'))
                ->map(fn ($file) => [
                    'name' => basename($file),
                    'size' => $backupsDisk->size($file),
                    'modified' => $backupsDisk->lastModified($file),
                ])
                ->sortByDesc('modified')
                ->take(10)
            : collect();

        return view('admin.settings.index', compact('backups', 'canManageBackups'));
    }

    /**
     * Crea un backup del database.
     *
     * Riservato al superadmin (audit sicurezza 2026-10-22, vedi index()):
     * dump dell'intero database multi-tenant, non del solo gruppo di chi
     * lo richiede. Prima era riservata a capo/sottocapo (già una
     * correzione rispetto a "qualunque utente autenticato"), ma restava
     * troppo ampia per un'azione che tocca i dati di TUTTE le associazioni.
     */
    public function backup()
    {
        if (! auth()->user()->isSuperadmin()) {
            abort(403);
        }

        // Artisan::call() non lancia un'eccezione se il comando fallisce:
        // senza controllare il codice di uscita, questa azione mostrava
        // "Backup creato con successo" anche quando il comando era andato
        // in errore (es. il bug sqlite_master su MySQL, mai notato per
        // questo stesso motivo).
        $exitCode = Artisan::call('app:backup-database');

        if ($exitCode !== Command::SUCCESS) {
            return back()->with('error', 'Backup non riuscito: controlla i log per i dettagli.');
        }

        return back()->with('status', 'Backup creato con successo.');
    }
}
