<?php

declare(strict_types=1);

namespace Magna\Contracts;

/**
 * Plugin contract: contributes widgets to the admin dashboard.
 * Semver-guaranteed from core 1.0. Wired to Filament in Stage 10.
 */
interface RegistersDashboardWidgets
{
    /**
     * Return Filament widget class names to surface on the admin dashboard.
     *
     * @return list<class-string>
     */
    public function dashboardWidgets(): array;
}
