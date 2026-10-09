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
        $declaredFulltextColumns = property_exists($this, 'fulltextable') ? (array) $this->fulltextable : [];

        // Ogni colonna qui finisce, su MySQL/MariaDB, direttamente in una
        // whereRaw() (MATCH (colonna) AGAINST (...)): oggi $fulltextable è
        // sempre una proprietà statica scritta nel modello, mai derivata
        // dalla richiesta, ma whitelistare il formato costa nulla e blocca
        // subito un futuro $fulltextable che diventasse anche solo
        // indirettamente derivato dall'input. Validato sempre (non solo sul
        // driver MySQL) così resta testabile anche su SQLite, dove questo
        // stesso codice viene eseguito nei test.
        foreach ($declaredFulltextColumns as $column) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column)) {
                throw new \InvalidArgumentException("Nome colonna fulltext non valido: {$column}");
            }
        }

        $fulltextColumns = $useFulltext ? $declaredFulltextColumns : [];

        $terms = explode(' ', $search);

        foreach ($terms as $term) {
            $query->where(function (Builder $q) use ($term, $searchable, $fulltextColumns) {
                foreach ($searchable as $i => $column) {
                    if (in_array($column, $fulltextColumns, true)) {
                        // Già validato come identificatore sicuro sopra.
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
