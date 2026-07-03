<?php

declare(strict_types=1);

namespace Magna\Contracts;

use Magna\Admin\Nav\NavGroup;

/**
 * Plugin contract: contributes a navigation group to the admin sidebar.
 * Semver-guaranteed from core 1.0.
 */
interface RegistersAdminNavigation
{
    public function adminNavigation(): NavGroup;
}
