<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    /**
     * Display the invitation activation form.
     * Validates the token: exists, not expired, user not already active.
     */
    public function show(string $token)
    {
        $user = $this->findValidUser($token);

        if (! $user) {
            return redirect()->route('filament.doctor.auth.login');
        }

        if ($user->isInvitationExpired()) {
            return view('invitation.expired');
        }

        return view('invitation.activate', ['token' => $token]);
    }

    /**
     * Process the invitation activation form.
     * Sets the user's password and activates the account.
     */
    public function activate(Request $request, string $token)
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $this->findValidUser($token);

        if (! $user || $user->isInvitationExpired()) {
            return view('invitation.expired');
        }

        $user->password = bcrypt($validated['password']);
        $user->is_active = true;
        $user->clearInvitationToken();
        $user->save();

        return redirect()->route('filament.doctor.auth.login')
            ->with('success', 'Cuenta activada. Iniciá sesión.');
    }

    /**
     * Find a user by their invitation token hash.
     * Returns null if no valid user is found (consumed, invalid, or already active).
     */
    private function findValidUser(string $token): ?User
    {
        $hash = hash('sha256', $token);

        $user = User::where('invitation_token', $hash)->first();

        if (! $user) {
            return null;
        }

        if ($user->isActive()) {
            return null;
        }

        return $user;
    }
}