<?php

declare(strict_types=1);

namespace Magna\Contracts;

/**
 * Plugin contract: contributes pages under the admin Settings section.
 * Semver-guaranteed from core 1.0. Wired to Filament in Stage 10.
 */
interface RegistersSettingsPages
{
    /**
     * Return Filament page class names to appear under Settings.
     *
     * @return list<class-string>
     */
    public function settingsPages(): array;
}
