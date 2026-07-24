<?php

declare(strict_types=1);

namespace Magna\Install;

final readonly class Requirement
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $passed,
        public bool $required,
        public string $help,
    ) {}
}
