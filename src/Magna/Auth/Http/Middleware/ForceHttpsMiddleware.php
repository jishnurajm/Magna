<?php

declare(strict_types=1);

namespace Magna\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Magna\Settings\SecuritySettings;
use Symfony\Component\HttpFoundation\Response;

class ForceHttpsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Now wired into the global 'web' middleware group (S1-11), which
        // runs on installer routes too — SecuritySettings reads from the
        // 'settings' table, which doesn't exist yet before/during install.
        // Fail open (no redirect) rather than 500 the whole installer flow;
        // matches the same try/catch pattern AuthServiceProvider::applySecurityConfig()
        // already uses for the identical "DB not ready" case.
        try {
            $forceHttps = SecuritySettings::get()->force_https;
        } catch (\Throwable) {
            return $next($request);
        }

        if ($forceHttps && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
