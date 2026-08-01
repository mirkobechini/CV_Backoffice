<?php

namespace App\Http\Requests\Concerns;

use Carbon\Carbon;

trait HandlesWarrantyExtension
{
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (is_array($data) && ($data['has_warranty_extension'] ?? false)) {
            $originalDate = $data['warranty_expiration_date'] ?? null;
            $extensionDuration = (int) ($data['warranty_extension_duration'] ?? 0);

            if ($originalDate && $extensionDuration > 0) {
                $data['warranty_expiration_date'] = Carbon::parse($originalDate)
                    ->addMonths($extensionDuration)
                    ->toDateString();
            }
        }

        return $data;
    }
}
