<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Group;

class DashboardController extends Controller
{
    /**
     * Elenco di tutte le associazioni (gruppi) con la loro licenza
     * corrente, per capo d'occhio: tier, limiti, scadenza.
     */
    public function index()
    {
        $groups = Group::withCount('users', 'vehicles')
            ->with('latestLicense')
            ->orderBy('name')
            ->get();

        return view('superadmin.dashboard', compact('groups'));
    }
}
