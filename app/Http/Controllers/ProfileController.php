<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Group;
use App\Models\User;
use App\Services\TwoFactorQrCodeGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request, TwoFactorQrCodeGenerator $qrCodeGenerator): View
    {
        $user = $request->user();

        // QR solo per un'attivazione in corso (segreto generato, non
        // ancora confermato): dopo la conferma non serve più mostrarlo.
        $twoFactorQrCode = ($user->two_factor_secret && ! $user->hasTwoFactorEnabled())
            ? $qrCodeGenerator->svg($user)
            : null;

        return view('profile.edit', [
            'user' => $user,
            'tokens' => $user->tokens()->orderByDesc('created_at')->get(),
            'twoFactorQrCode' => $twoFactorQrCode,
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
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
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
            // telegram_link_token è un segreto temporaneo (15 minuti, come
            // un token di reset password), non un dato personale da
            // esportare.
            'notification_settings' => $user->notificationSettings
                ->reject(fn ($s) => $s->key === 'telegram_link_token')
                ->map(fn ($s) => [
                    'key' => $s->key,
                    'value' => $s->value,
                    'updated_at' => $s->updated_at?->toIso8601String(),
                ])->values(),
            'api_tokens' => $user->tokens->map(fn ($t) => [
                'name' => $t->name,
                'created_at' => $t->created_at?->toIso8601String(),
                'last_used_at' => $t->last_used_at?->toIso8601String(),
            ]),
            'activity_log' => Activity::where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($a) => [
                    'description' => $a->description,
                    'subject_type' => $a->subject_type,
                    'subject_id' => $a->subject_id,
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
        ];

        $filename = 'dati-personali-'.$user->id.'-'.now()->format('Y-m-d').'.json';

        return response()->json($data)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }
}
