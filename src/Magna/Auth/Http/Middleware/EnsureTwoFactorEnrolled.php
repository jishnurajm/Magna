<?php

declare(strict_types=1);

namespace Magna\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Magna\Users\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * S1-06: closes the "mandatory 2FA" gap where a role with
 * requires_two_factor=true never actually forced enrollment —
 * LoginController::requiresTwoFactor() only challenges a user who has
 * *already* enrolled, so a user who never visits the (previously optional)
 * enrollment link kept full password-only access forever.
 *
 * Redirects any authenticated user whose role requires 2FA but who hasn't
 * confirmed enrollment (two_factor_confirmed_at === null) to the mandatory
 * setup page, on every request, until they complete it. The setup page's
 * own routes and logout must never be wrapped by this middleware (or must
 * be excluded here) to avoid a redirect loop.
 */
class EnsureTwoFactorEnrolled
{
    /** @param  Closure(Request): Response  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        $roleRequires = $user->roles->contains(
            fn ($role): bool => (bool) $role->requires_two_factor,
        );

        if (! $roleRequires) {
            return $next($request);
        }

        if ($request->routeIs('auth.two-factor.setup', 'auth.two-factor.setup.store', 'auth.logout')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(
                ['message' => 'Two-factor authentication setup is required before continuing.'],
                Response::HTTP_FORBIDDEN,
            );
        }

        return redirect()->route('auth.two-factor.setup');
    }
}
