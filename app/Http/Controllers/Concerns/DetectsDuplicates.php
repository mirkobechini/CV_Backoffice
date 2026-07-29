<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

trait DetectsDuplicates
{
    protected function findDuplicate(string $modelClass, array $criteria, int $minutesThreshold = 5): ?Model
    {
        $query = $modelClass::query();

        foreach ($criteria as $field => $value) {
            if ($value === null) {
                $query->whereNull($field);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->where('created_at', '>=', Carbon::now()->subMinutes($minutesThreshold))
            ->latest('id')
            ->first();
    }
}
