<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IssuePartClassificationException;
use App\Http\Controllers\Concerns\ChecksLicenseFeature;
use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Models\License;
use App\Services\IssuePartClassifierService;
use Illuminate\Http\Request;

/**
 * Strumento una tantum per allineare i guasti creati prima dell'introduzione
 * di Issue::part (obbligatoria da subito per i nuovi, ma i vecchi restano
 * "part" null finché non vengono rivisti qui): un suggerimento AI per
 * ciascuno, sempre da confermare o correggere a mano prima del salvataggio,
 * mai applicato automaticamente. Incluso solo da una licenza Pro in su
 * (vedi ADR §16).
 */
class IssuePartBackfillController extends Controller
{
    use ChecksLicenseFeature;

    public function index(Request $request)
    {
        $this->authorize('update', Issue::class);
        $this->abortUnlessLicenseHasFeature($request->user(), License::FEATURE_AI_BACKFILL);

        $issues = Issue::with('vehicle')
            ->forCurrentUser()
            ->whereNull('part')
            ->orderBy('event_date')
            ->get();

        return view('admin.issues.backfill-parts', compact('issues'));
    }

    /**
     * Endpoint JSON chiamato via fetch dal browser un blocco alla volta
     * (vedi backfill-parts.blade.php): classificare TUTTI i guasti in
     * un'unica richiesta sincrona poteva superare il timeout del proxy
     * davanti a Laravel Cloud (Cloudflare) quando i guasti da allineare
     * erano molti, interrompendo la richiesta senza alcun risultato.
     * A blocchi piccoli, ogni richiesta resta sotto qualunque timeout.
     */
    public function suggest(Request $request)
    {
        $this->authorize('update', Issue::class);
        $this->abortUnlessLicenseHasFeature($request->user(), License::FEATURE_AI_BACKFILL);

        $data = $request->validate([
            'issue_ids' => 'required|array|max:25',
            'issue_ids.*' => 'integer',
        ]);

        $issues = Issue::forCurrentUser()
            ->whereNull('part')
            ->whereIn('id', $data['issue_ids'])
            ->get();

        if ($issues->isEmpty()) {
            return response()->json(['suggestions' => []]);
        }

        try {
            $suggestions = app(IssuePartClassifierService::class)->suggestParts($issues);
        } catch (IssuePartClassificationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['suggestions' => $suggestions]);
    }

    public function store(Request $request)
    {
        $this->authorize('update', Issue::class);
        $this->abortUnlessLicenseHasFeature($request->user(), License::FEATURE_AI_BACKFILL);

        $data = $request->validate([
            'parts' => 'required|array',
            'parts.*' => ['nullable', 'in:'.implode(',', Issue::PARTS)],
        ]);

        $issueIds = Issue::forCurrentUser()
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
