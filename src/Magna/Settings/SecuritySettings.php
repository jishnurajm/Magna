<?php

declare(strict_types=1);

namespace Magna\Settings;

class SecuritySettings extends Settings
{
    /**
     * Redirect HTTP requests to HTTPS. Applied by ForceHttpsMiddleware on
     * every non-CLI, non-HTTPS request.
     */
    public bool $force_https = false;

    /**
     * Block access to all routes until the registered user confirms their
     * email address. When true, RegisterController redirects to the
     * email verification notice instead of logging the user in immediately.
     */
    public bool $require_email_verification = false;

    /**
     * Web session lifetime in minutes. Overrides config('session.lifetime')
     * at service-provider boot so the value is effective for every request.
     */
    public int $session_lifetime = 120;
}
