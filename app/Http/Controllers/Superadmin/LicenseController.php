<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLicenseRequest;
use App\Models\DiscountCode;
use App\Models\Group;
use App\Models\License;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LicenseController extends Controller
{
    /**
     * Form di assegnazione/rinnovo licenza per un gruppo. Rinnovare = creare
     * una nuova riga (storico naturale), non modificare quella corrente.
     */
    public function create(Group $group)
    {
        $group->load('latestLicense');

        return view('superadmin.licenses.create', [
            'group' => $group,
            'currentLicense' => $group->currentLicense(),
            'tierPresets' => License::TIER_PRESETS,
        ]);
    }

    public function store(StoreLicenseRequest $request, Group $group)
    {
        $data = $request->validated();

        $discountCode = null;
        if (! empty($data['discount_code'])) {
            $discountCode = DiscountCode::where('code', strtoupper($data['discount_code']))->first();

            if (! $discountCode || ! $discountCode->isUsable()) {
                return back()->withErrors(['discount_code' => 'Codice sconto non valido, scaduto o esaurito.'])->withInput();
            }
        }

        $finalPrice = License::computeFinalPrice($data['list_price_eur'] ?? null, $discountCode?->percent);

        DB::transaction(function () use ($data, $group, $discountCode, $finalPrice) {
            $group->licenses()->create([
                'tier' => $data['tier'],
                'max_vehicles' => $data['max_vehicles'] ?? null,
                'max_users' => $data['max_users'] ?? null,
                'features' => $data['features'] ?? [],
                'list_price_eur' => $data['list_price_eur'] ?? null,
                'discount_code_id' => $discountCode?->id,
                'discount_percent' => $discountCode?->percent,
                'final_price_eur' => $finalPrice,
                'starts_at' => $data['starts_at'],
                'expires_at' => $data['expires_at'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $discountCode?->redeem();
        });

        return redirect()->route('superadmin.dashboard')->with('status', "Licenza assegnata a {$group->name}.");
    }
}
