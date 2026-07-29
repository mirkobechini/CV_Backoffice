<?php

namespace App\Http\Controllers\Concerns;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

trait SortableAndGroupable
{
    /**
     * Applica ordinamento.
     * $sortMap: [ 'field' => 'colonna_db' | callable ]
     * - Se stringa → orderBy() direttamente nel DB
     * - Se callable → sorting in memoria (per accessor/relazioni)
     */
    protected function applySorting(Builder $query, string $sortBy, string $sortDir, array $sortMap): Collection
    {
        $entry = $sortMap[$sortBy] ?? null;

        if ($entry === null) {
            return $query->get();
        }

        // Ordinamento via DB
        if (is_string($entry)) {
            return $query->orderBy($entry, $sortDir)->get();
        }

        // Ordinamento in memoria per accessor/relazioni
        $collection = $query->get();
        return $sortDir === 'desc'
            ? $collection->sortByDesc($entry)->values()
            : $collection->sortBy($entry)->values();
    }

    /**
     * Applica ordinamento su una collection già esistente.
     * Utile quando devi elaborare la collection prima di ordinarla.
     */
    protected function applySortingToCollection(Collection $collection, string $sortBy, string $sortDir, array $sortMap): Collection
    {
        $entry = $sortMap[$sortBy] ?? null;

        if ($entry === null || !is_callable($entry)) {
            return $collection;
        }

        return $sortDir === 'desc'
            ? $collection->sortByDesc($entry)->values()
            : $collection->sortBy($entry)->values();
    }

    protected function applyGrouping($collection, ?string $groupBy, callable $groupCallback): ?Collection
    {
        if ($groupBy === null) {
            return null;
        }
        return $collection->groupBy($groupCallback);
    }

    protected function groupToggleUrl(string $field, ?string $currentGroupBy, string $routeName): string
    {
        $query = request()->query();
        if ($currentGroupBy === $field) {
            unset($query['group_by']);
        } else {
            $query['group_by'] = $field;
        }
        return route($routeName, $query);
    }

    protected function sortToggleUrl(string $field, ?string $currentSortBy, string $currentSortDir, string $routeName): string
    {
        $query = request()->query();
        $nextDirection = $currentSortBy === $field && $currentSortDir === 'asc' ? 'desc' : 'asc';
        $query['sort_by'] = $field;
        $query['sort_dir'] = $nextDirection;
        return route($routeName, $query);
    }

    protected function sortIcon(string $field, ?string $currentSortBy, string $currentSortDir): string
    {
        if ($currentSortBy !== $field) return '↕';
        return $currentSortDir === 'asc' ? '↑' : '↓';
    }
}