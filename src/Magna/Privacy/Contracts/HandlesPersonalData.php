<?php

declare(strict_types=1);

namespace Magna\Privacy\Contracts;

use Magna\Users\User;

/**
 * Core-side alias of the plugin-sdk HandlesPersonalData contract.
 * Core modules (not plugins) that store personal data implement this interface.
 *
 * @see \Magna\Contracts\HandlesPersonalData — the plugin-sdk version for plugins
 */
interface HandlesPersonalData
{
    /** @return array<string, mixed> */
    public function exportPersonalData(User $user): array;

    public function erasePersonalData(User $user): void;
}
