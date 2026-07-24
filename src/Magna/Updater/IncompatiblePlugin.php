<?php

declare(strict_types=1);

namespace Magna\Updater;

/**
 * An enabled plugin whose manifest compat range doesn't satisfy a candidate
 * core version. Carries enough detail (installed version, required range) for
 * the admin to make an informed uninstall/force decision in the resolution UI.
 */
final readonly class IncompatiblePlugin
{
    public function __construct(
        public string $name,
        public string $displayName,
        public string $installedVersion,
        public string $requiredCompat,
    ) {}

    /**
     * @return array{name: string, displayName: string, installedVersion: string, requiredCompat: string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'displayName' => $this->displayName,
            'installedVersion' => $this->installedVersion,
            'requiredCompat' => $this->requiredCompat,
        ];
    }
}
