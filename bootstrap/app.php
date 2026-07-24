<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Magna\Admin\Notifications\NotificationRecipients;
use Magna\Auth\Http\Middleware\ForceHttpsMiddleware;
use Magna\Auth\Http\Middleware\SecurityHeadersMiddleware;
use Magna\Install\Http\Middleware\RedirectIfNotInstalled;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Prepend globally so the "not installed" gate runs before anything
        // else — in particular before the Filament panel's Authenticate
        // middleware, which (mounted at "/") would otherwise redirect guests
        // to /login before the installer redirect can fire. It only checks a
        // lock file, so it needs no session.
        $middleware->prepend(RedirectIfNotInstalled::class);

        // S1-11: ForceHttpsMiddleware was registered as a middleware alias
        // but never actually attached to any route or group — toggling
        // "Force HTTPS" in Security Settings had zero runtime effect. Wired
        // in here, ahead of everything else in the web group, so a plain
        // HTTP request is redirected before CORS/security-header processing
        // even runs. DB-backed (reads SecuritySettings), safe to run this
        // late in the global stack since RedirectIfNotInstalled (prepended
        // above) has already handled the pre-install, DB-unavailable case.
        $middleware->web(prepend: [ForceHttpsMiddleware::class, HandleCors::class]);
        $middleware->web(append: [SecurityHeadersMiddleware::class]);

        $middleware->api(prepend: [HandleCors::class]);
        $middleware->api(append: [SecurityHeadersMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Production-only: local/testing already surface errors directly
        // (debug page, test failures) — the bell is for catching things in
        // an environment nobody is actively watching a terminal for.
        $exceptions->reportable(function (Throwable $e): void {
            if (! app()->environment('production')) {
                return;
            }

            // 4xx-class exceptions (validation, auth, 404, CSRF token
            // mismatch) are expected traffic noise, not something an admin
            // needs paged for — only genuine 5xx/unclassified failures
            // reach the bell.
            $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            if ($statusCode < 500) {
                return;
            }

            // The bell itself must never mask the real failure. If the cache or
            // database backing this is unavailable (e.g. a misconfigured or
            // not-yet-installed site), swallow it so the operator sees the
            // original exception, not a secondary one from the reporter.
            try {
                // Rate-limited per distinct exception (class + message), not
                // per occurrence — a tight crash loop must not flood the bell
                // with hundreds of identical rows.
                $key = 'magna.admin.exception-notified.'.md5($e::class.'|'.$e->getMessage());
                if (Cache::has($key)) {
                    return;
                }
                Cache::put($key, true, now()->addHour());

                NotificationRecipients::notifyDashboard(
                    'Unexpected error',
                    $e->getMessage() !== '' ? $e->getMessage() : $e::class,
                    'danger',
                );
            } catch (Throwable) {
                // Reporting is best-effort; never let it escalate.
            }
        });
    })->create();
