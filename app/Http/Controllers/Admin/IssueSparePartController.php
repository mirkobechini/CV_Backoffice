<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIssueSparePartsRequest;
use App\Models\Issue;
use App\Models\IssueSparePart;
use Illuminate\Http\Request;

class IssueSparePartController extends Controller
{
    /**
     * Crea più pezzi insieme per lo stesso guasto. Se la data ordine è
     * lasciata vuota su una riga, eredita quella della prima riga
     * compilata nello stesso invio (la data arrivo resta sempre libera,
     * non eredita mai).
     */
    public function store(StoreIssueSparePartsRequest $request, Issue $issue)
    {
        $this->authorize('update', $issue);

        $parts = $request->validated('parts');

        $defaultOrderedAt = collect($parts)->pluck('ordered_at')->first(fn ($v) => ! empty($v));

        foreach ($parts as $part) {
            $issue->spareParts()->create([
                'name' => $part['name'],
                'notes' => $part['notes'] ?? null,
                'ordered_at' => ($part['ordered_at'] ?? null) ?: $defaultOrderedAt,
                'arrived_at' => $part['arrived_at'] ?? null,
            ]);
        }

        return redirect()->route('admin.issues.show', $issue)->with('status', 'Pezzi aggiunti con successo.');
    }

    public function update(Request $request, Issue $issue, IssueSparePart $sparePart)
    {
        $this->authorize('update', $issue);
        abort_unless($sparePart->issue_id === $issue->id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'ordered_at' => ['nullable', 'date'],
            'arrived_at' => ['nullable', 'date'],
        ]);

        $sparePart->update($data);

        return redirect()->route('admin.issues.show', $issue)->with('status', 'Pezzo aggiornato con successo.');
    }

    public function destroy(Issue $issue, IssueSparePart $sparePart)
    {
        $this->authorize('update', $issue);
        abort_unless($sparePart->issue_id === $issue->id, 404);

        $sparePart->delete();

        return redirect()->route('admin.issues.show', $issue)->with('status', 'Pezzo eliminato con successo.');
    }
}
