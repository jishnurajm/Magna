<?php

declare(strict_types=1);

namespace Magna\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Magna\Auth\LoginThrottle;
use Magna\Users\User;

class LoginController extends Controller
{
    public function __construct(private LoginThrottle $throttle) {}

    public function showForm(): View
    {
        return view('magna::login');
    }

    public function attempt(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($this->throttle->isLocked($request)) {
            $seconds = $this->throttle->availableIn($request);

            return back()->withErrors([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', [
                    'seconds' => $seconds,
                ]),
            ])->withInput($request->only('email'));
        }

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $this->throttle->hit($request);

            return back()->withErrors([
                'email' => __('These credentials do not match our records.'),
            ])->withInput($request->only('email'));
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();

            return back()->withErrors([
                'email' => __('This account has been suspended.'),
            ])->withInput($request->only('email'));
        }

        $this->throttle->clear($request);

        // If any of the user's roles require 2FA and the user has confirmed
        // enrollment, put them in the pending-challenge state.
        if ($this->requiresTwoFactor($user)) {
            Auth::logout();
            $request->session()->put('auth.two_factor_user_id', $user->getKey());
            $request->session()->put('auth.two_factor_remember', $request->boolean('remember'));

            return redirect()->route('auth.two-factor.challenge');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function requiresTwoFactor(User $user): bool
    {
        $hasEnrolled = $user->two_factor_confirmed_at !== null;

        $roleRequires = $user->roles->contains(
            fn ($role): bool => (bool) $role->requires_two_factor,
        );

        return $hasEnrolled && $roleRequires;
    }
}
