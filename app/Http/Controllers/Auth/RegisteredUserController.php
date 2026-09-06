<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Il primo utente registrato crea un gruppo e diventa capo.
        // Se ci sono già utenti, solo un capo può registrarne di nuovi.
        $isFirstUser = User::count() === 0;

        if (!$isFirstUser) {
            $currentUser = Auth::user();
            if (!$currentUser || !$currentUser->canManageData()) {
                abort(403, 'Solo un capo può registrare nuovi utenti.');
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if ($isFirstUser) {
            // Primo utente: crea un gruppo di default e diventa capo.
            $group = Group::create([
                'name' => 'Associazione di default',
                'invite_code' => Group::generateInviteCode(),
            ]);
            $group->addUser($user, Group::ROLE_CAPO);
        } else {
            // Utente successivo: entra nel gruppo del capo corrente come membro.
            $currentUser = Auth::user();
            $group = $currentUser->activeGroup();
            if ($group) {
                $group->addUser($user, Group::ROLE_MEMBER);
            }
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
