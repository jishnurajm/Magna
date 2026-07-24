<?php

declare(strict_types=1);

namespace Magna\Marketplace;

/**
 * Runs Composer on the host. Abstracted behind an interface so the installer
 * can be tested without actually shelling out.
 */
interface ComposerRunner
{
    /** Whether a usable Composer binary was found on this server. */
    public function isAvailable(): bool;

    /**
     * Run a Composer command (e.g. ['require', 'acme/forum']). `--no-interaction`
     * is always appended by the implementation.
     *
     * @param  list<string>  $args
     */
    public function run(array $args, int $timeout = 300): ComposerResult;
}
