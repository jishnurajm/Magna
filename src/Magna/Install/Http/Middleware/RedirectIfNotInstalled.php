<?php

declare(strict_types=1);

namespace Magna\Install\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Magna\Install\Installer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until Magna is installed, every web request is sent to the installer.
 */
class RedirectIfNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Installer::isInstalled() || $request->is('install', 'install/*')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
