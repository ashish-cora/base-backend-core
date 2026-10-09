<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Fortify;

/**
 * One-go 2FA toggle: validates the current password inline, then enables or
 * disables two-factor in the same POST.
 *
 * This bypasses Fortify's `password.confirm` redirect (which drops the POST
 * body and forces a second click) by calling Fortify's actions directly
 * instead of POSTing to Fortify's routes. The session confirmation timestamp
 * is still set (same key Fortify uses) so follow-on Fortify routes
 * (confirm-code, qr-code, secret-key, recovery-codes) keep passing.
 */
class TwoFactorController extends Controller
{
    public function store(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ]);

        $enable($request->user());

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED);
    }

    public function destroy(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ]);

        $disable($request->user());

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return back()->with('status', Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED);
    }
}
