<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

trait Searchable
{
    /**
     * Apply a search query to the builder.
     * Each model should define a $searchable property with the columns to search.
     *
     * Su MySQL/MariaDB usa FULLTEXT MATCH per colonne dichiarate in $fulltextable,
     * e LIKE per colonne corte (status, type, targa, ecc.).
     * Su SQLite usa LIKE puro (fallback).
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

        $driver = DB::getDriverName();
        $useFulltext = in_array($driver, ['mysql', 'mariadb'], true);
        $fulltextColumns = $useFulltext && property_exists($this, 'fulltextable')
            ? (array) $this->fulltextable
            : [];

        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $query->where(function (Builder $q) use ($term, $searchable, $fulltextColumns) {
                foreach ($searchable as $i => $column) {
                    if (in_array($column, $fulltextColumns, true)) {
                        // FULLTEXT MATCH (solo MySQL/MariaDB)
                        if ($i === 0) {
                            $q->whereRaw("MATCH ({$column}) AGAINST (? IN BOOLEAN MODE)", [$term . '*']);
                        } else {
                            $q->orWhereRaw("MATCH ({$column}) AGAINST (? IN BOOLEAN MODE)", [$term . '*']);
                        }
                    } else {
                        // LIKE per colonne corte (VARCHAR breve)
                        $method = $i === 0 ? 'where' : 'orWhere';
                        $q->$method($column, 'like', '%' . $term . '%');
                    }
                }
            });
        }

        return $query;
    }
}
