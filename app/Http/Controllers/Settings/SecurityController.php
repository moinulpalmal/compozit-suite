<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    /**
     * Show the user's security settings page.
     *
     * The unused `TwoFactorAuthenticationRequest` is load-bearing: resolving it
     * runs its authorization, which is what gates the page. The password policy
     * arrives via the `passwordPolicy` shared prop, not from here.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        return Inertia::render('settings/security');
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
