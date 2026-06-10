<?php

namespace App\Http\Responses\Filament;

use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ActiveLoginResponse implements LoginResponse
{
    public function toResponse($request): RedirectResponse
    {
        $user = Auth::user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->to(filament()->getLoginUrl())
                ->with('error', 'Your account has been suspended. Please contact the administrator.');
        }

        return redirect()->intended(filament()->getUrl());
    }
}