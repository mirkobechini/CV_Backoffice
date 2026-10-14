<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IssuePartClassificationException;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Services\IssuePartClassifierService;
use Illuminate\Http\Request;

/**
 * Strumento una tantum per allineare i guasti creati prima dell'introduzione
 * di Issue::part (obbligatoria da subito per i nuovi, ma i vecchi restano
 * "part" null finché non vengono rivisti qui): un suggerimento AI per
 * ciascuno, sempre da confermare o correggere a mano prima del salvataggio,
 * mai applicato automaticamente.
 */
class IssuePartBackfillController extends Controller
{
    public function index()
    {
        $this->authorize('update', Issue::class);

        $issues = Issue::with('vehicle')
            ->whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->whereNull('part')
            ->orderBy('event_date')
            ->get();

        return view('admin.issues.backfill-parts', compact('issues'));
    }

    public function suggest()
    {
        $this->authorize('update', Issue::class);

        $issues = Issue::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->whereNull('part')
            ->get();

        if ($issues->isEmpty()) {
            return redirect()->route('admin.issues.backfill-parts.index');
        }

        try {
            $suggestions = app(IssuePartClassifierService::class)->suggestParts($issues);
        } catch (IssuePartClassificationException $e) {
            return redirect()->route('admin.issues.backfill-parts.index')
                ->with('error', "Impossibile generare i suggerimenti: {$e->getMessage()}");
        }

        return redirect()->route('admin.issues.backfill-parts.index')
            ->with('part_suggestions', $suggestions)
            ->with('status', 'Suggerimenti generati: verifica e correggi prima di salvare.');
    }

    public function store(Request $request)
    {
        $this->authorize('update', Issue::class);

        $data = $request->validate([
            'parts' => 'required|array',
            'parts.*' => ['nullable', 'in:'.implode(',', Issue::PARTS)],
        ]);

        $issueIds = Issue::whereHas('vehicle', fn ($q) => $q->forCurrentUser())
            ->whereNull('part')
            ->pluck('id');

        $updated = 0;

        foreach ($data['parts'] as $issueId => $part) {
            // Solo gli id che la query qui sopra conferma appartenere al
            // gruppo corrente e avere ancora part nullo: senza questo
            // controllo, un id manomesso nel form avrebbe potuto aggiornare
            // il guasto di un altro gruppo.
            if (! $part || ! $issueIds->contains((int) $issueId)) {
                continue;
            }

            Issue::whereKey($issueId)->update(['part' => $part]);
            $updated++;
        }

        return redirect()->route('admin.issues.backfill-parts.index')
            ->with('status', "{$updated} ".($updated === 1 ? 'guasto allineato.' : 'guasti allineati.'));
    }
}
