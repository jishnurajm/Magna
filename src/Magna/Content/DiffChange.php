<?php

declare(strict_types=1);

namespace Magna\Content;

final class DiffChange
{
    public function __construct(
        public readonly DiffChangeType $type,
        public readonly string $contentTypeHandle,
        public readonly ?string $column,
        public readonly bool $destructive,
        public readonly string $description,
    ) {}
}
