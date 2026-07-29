<?php

namespace App\Http\Controllers\Concerns;
use Illuminate\Support\Collection;

trait SortableAndGroupable
{
    protected function applySorting($collection, string $sortBy, string $sortDir, callable $sortCallback): Collection
    {
        return $sortDir === 'desc'
            ? $collection->sortByDesc($sortCallback)->values()
            : $collection->sortBy($sortCallback)->values();
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