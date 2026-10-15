<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSuperadminGroupRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GroupController extends Controller
{
    /**
     * Form di onboarding di una nuova associazione: a differenza di
     * admin.groups.store (un capo già autenticato crea il proprio
     * gruppo), qui il superadmin non appartiene al gruppo che crea, quindi
     * serve anche creare da zero l'account del primo capo.
     */
    public function create()
    {
        return view('superadmin.groups.create');
    }

    public function store(StoreSuperadminGroupRequest $request)
    {
        $data = $request->validated();

        $group = DB::transaction(function () use ($data) {
            $group = Group::create([
                'name' => $data['name'],
                'invite_code' => Group::generateInviteCode(),
            ]);

            $capo = User::create([
                'name' => $data['capo_name'],
                'email' => $data['capo_email'],
                'password' => Hash::make($data['capo_password']),
            ]);

            $group->addUser($capo, Group::ROLE_CAPO);

            return $group;
        });

        return redirect()->route('superadmin.licenses.create', $group)
            ->with('status', "Associazione \"{$group->name}\" creata. Assegna ora una licenza.");
    }
}
