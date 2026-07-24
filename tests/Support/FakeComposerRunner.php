<?php

declare(strict_types=1);

namespace Tests\Support;

use Magna\Marketplace\ComposerResult;
use Magna\Marketplace\ComposerRunner;

/** Records the Composer commands it's asked to run, without shelling out. */
class FakeComposerRunner implements ComposerRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    public bool $available = true;

    public int $exitCode = 0;

    public string $output = 'ok';

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function run(array $args, int $timeout = 300): ComposerResult
    {
        $this->commands[] = $args;

        return new ComposerResult($this->exitCode, $this->output);
    }
}
