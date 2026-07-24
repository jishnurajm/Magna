<?php

declare(strict_types=1);

namespace Magna\Auth\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Magna\Auth\TwoFactorService;
use Magna\Users\User;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function showForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.two_factor_user_id')) {
            return redirect()->route('auth.login');
        }

        return view('magna::two-factor-challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.two_factor_user_id');

        if ($userId === null) {
            return redirect()->route('auth.login');
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if ($user === null) {
            $request->session()->forget(['auth.two_factor_user_id', 'auth.two_factor_remember']);

            return redirect()->route('auth.login');
        }

        $code = $request->string('code')->toString();
        $recoveryCode = $request->string('recovery_code')->toString();

        if ($code !== '' && $this->verifyTotp($user, $code)) {
            return $this->completeLogin($request, $user);
        }

        if ($recoveryCode !== '' && $this->verifyRecovery($user, $recoveryCode)) {
            return $this->completeLogin($request, $user);
        }

        return back()->withErrors([
            'code' => __('The provided code was invalid.'),
        ]);
    }

    private function verifyTotp(User $user, string $code): bool
    {
        $secret = $user->two_factor_secret;

        return $secret !== null && $this->twoFactor->verify($secret, $code);
    }

    private function verifyRecovery(User $user, string $input): bool
    {
        $encoded = $user->two_factor_recovery_codes;

        if ($encoded === null) {
            return false;
        }

        /** @var list<string> $codes */
        $codes = json_decode($encoded, true) ?? [];

        $remaining = $this->twoFactor->redeemRecoveryCode($codes, $input);

        if ($remaining === false) {
            return false;
        }

        $user->forceFill(['two_factor_recovery_codes' => json_encode($remaining)])->save();

        return true;
    }

    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        $remember = (bool) $request->session()->pull('auth.two_factor_remember', false);

        $request->session()->forget('auth.two_factor_user_id');

        Auth::login($user, $remember);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
