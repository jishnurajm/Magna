<?php

declare(strict_types=1);

namespace Magna\Plugins\Exceptions;

use RuntimeException;

final class PluginNotFoundException extends RuntimeException
{
    public function __construct(string $name)
    {
        parent::__construct("Plugin [{$name}] was not found. Run `composer require {$name}` first.");
    }
}
