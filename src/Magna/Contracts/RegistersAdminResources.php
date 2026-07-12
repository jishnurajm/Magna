<?php

declare(strict_types=1);

namespace Magna\Contracts;

/**
 * Plugin contract: registers Filament resource classes with the admin panel.
 * Plugins implement this to expose their own CRUD screens in the panel.
 */
interface RegistersAdminResources
{
    /**
     * Return Filament resource class names to register with the panel.
     *
     * @return list<class-string>
     */
    public function adminResources(): array;
}
