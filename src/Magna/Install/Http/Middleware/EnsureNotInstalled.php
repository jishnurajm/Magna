<?php

declare(strict_types=1);

namespace Magna\Install\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Magna\Install\Installer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Once installed, the installer no longer exists: its routes 404. An
 * exposed installer on a live site is a takeover vector — this guard is
 * security-critical.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Installer::isInstalled(), 404);

        return $next($request);
    }
}
