<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'tokens' => $request->user()->tokens()->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Crea un nuovo token API.
     */
    public function createToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token_name' => ['required', 'string', 'max:255'],
        ]);

        $token = $request->user()->createToken($data['token_name']);

        return Redirect::route('profile.edit')
            ->with('status', 'token-created')
            ->with('plainTextToken', $token->plainTextToken);
    }

    /**
     * Revoca un token API.
     */
    public function revokeToken(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return Redirect::route('profile.edit')->with('status', 'token-revoked');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Se l'utente è capo di un gruppo, deve trasferire il ruolo a un altro membro
        // prima di eliminare l'account (o confermare esplicitamente l'eliminazione).
        $capoGroups = $user->groups()->wherePivot('role', Group::ROLE_CAPO)->get();

        foreach ($capoGroups as $group) {
            $successorId = $request->input("successor_{$group->id}");

            if ($successorId) {
                $successor = User::find($successorId);
                if ($successor && $group->users()->where('users.id', $successorId)->exists()) {
                    $group->setUserRole($successor, Group::ROLE_CAPO);
                }
            }
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }

    /**
     * Esporta i dati personali dell'utente (GDPR right to data portability).
     */
    public function exportData(Request $request)
    {
        $user = $request->user();

        $data = [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'groups' => $user->groups->map(fn ($group) => [
                'name' => $group->name,
                'role' => $group->pivot->role,
            ]),
            'notifications' => $user->notifications->map(fn ($n) => [
                'type' => $n->type,
                'title' => $n->title,
                'message' => $n->message,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
        ];

        $filename = 'dati-personali-'.$user->id.'-'.now()->format('Y-m-d').'.json';

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
