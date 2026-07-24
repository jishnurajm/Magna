<?php

declare(strict_types=1);

namespace Magna\Marketplace;

/** The outcome of a single Composer invocation. */
final class ComposerResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $output,
    ) {}

    public function successful(): bool
    {
        return $this->exitCode === 0;
    }
}
