<?php

declare(strict_types=1);

namespace Magna\Backup;

final class BackupResult
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly int $sizeBytes,
        public readonly ?string $secondaryDisk = null,
        public readonly ?string $secondaryPath = null,
    ) {}
}
