<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Searchable
{
    /**
     * Apply a search query to the builder.
     * Each model should define a $searchable property with the columns to search.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $searchable = property_exists($this, 'searchable') ? $this->searchable : [];

        if (empty($searchable)) {
            return $query;
        }

        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $query->where(function (Builder $q) use ($term, $searchable) {
                foreach ($searchable as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $q->$method($column, 'like', '%' . $term . '%');
                }
            });
        }

        return $query;
    }
}
