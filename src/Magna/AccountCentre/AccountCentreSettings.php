<?php

declare(strict_types=1);

namespace Magna\AccountCentre;

use Magna\Settings\Attributes\Secret;
use Magna\Settings\Settings;

/**
 * This site's connection to a Magna Account (managemagna.jrstudios.dev) —
 * not a CMS admin login. A site connects to exactly one account at a time;
 * reconnecting simply overwrites these values. Uses the same encrypted-
 * settings store as the rest of core (Magna\Settings\SettingsRepository),
 * so the token is never at rest in plaintext.
 */
class AccountCentreSettings extends Settings
{
    public bool $connected = false;

    /** Cached for display only — the source of truth is managemagna.jrstudios.dev. */
    public ?string $accountName = null;

    public ?string $accountEmail = null;

    /** Bearer token presented on every authenticated call to Update Manager's account endpoints. */
    #[Secret]
    public ?string $token = null;

    public ?string $connectedAt = null;
}
