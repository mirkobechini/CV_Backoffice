<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscountCodeRequest;
use App\Models\DiscountCode;

class DiscountCodeController extends Controller
{
    public function index()
    {
        $discountCodes = DiscountCode::orderByDesc('id')->get();

        return view('superadmin.discount-codes.index', compact('discountCodes'));
    }

    public function create()
    {
        return view('superadmin.discount-codes.create');
    }

    public function store(StoreDiscountCodeRequest $request)
    {
        $data = $request->validated();

        DiscountCode::create([
            'code' => $data['code'] ? strtoupper($data['code']) : DiscountCode::generateCode(),
            'percent' => $data['percent'],
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => true,
        ]);

        return redirect()->route('superadmin.discount-codes.index')->with('status', 'Codice sconto creato.');
    }

    /**
     * Disattiva/riattiva un codice sconto (toggle, non elimina: i codici
     * già usati restano collegati alle licenze che li hanno applicati).
     */
    public function toggle(DiscountCode $discountCode)
    {
        $discountCode->update(['active' => ! $discountCode->active]);

        return back()->with('status', $discountCode->active ? 'Codice riattivato.' : 'Codice disattivato.');
    }
}
