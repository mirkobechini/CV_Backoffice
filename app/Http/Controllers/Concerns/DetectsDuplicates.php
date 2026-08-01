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
            } elseif ($this->isDateString($value)) {
                // whereDate evita mismatch tra stringa "2025-02-01" e il cast Date
                $query->whereDate($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query->where('created_at', '>=', Carbon::now()->subMinutes($minutesThreshold))
            ->latest('id')
            ->first();
    }

    private function isDateString(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}[-\/]\d{2}[-\/]\d{2}$/', $value);
    }
}
