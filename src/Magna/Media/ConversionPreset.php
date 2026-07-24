<?php

declare(strict_types=1);

namespace Magna\Media;

final class ConversionPreset
{
    public function __construct(
        public readonly string $name,
        public readonly int $width,
        public readonly int $height,
        public readonly bool $fit = true,
        public readonly bool $generateWebP = true,
        public readonly bool $generateAvif = true,
    ) {}
}
