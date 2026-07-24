<?php

declare(strict_types=1);

namespace Magna\Backup\Exceptions;

use RuntimeException;

class BackupConfigurationException extends RuntimeException
{
    public static function nothingSelected(): self
    {
        return new self('Backup settings have nothing selected to back up (database, files, and config export are all disabled).');
    }

    public static function collidesWithMediaDisk(): self
    {
        return new self('Refusing to run: the backup destination currently resolves to the same disk/bucket as the Storage settings media disk. Fix this in Backup Manager settings before running.');
    }

    public static function secondaryCollidesWithMediaDisk(): self
    {
        return new self('Refusing to run: the secondary backup destination currently resolves to the same disk/bucket as the Storage settings media disk. Fix this in Backup Manager settings before running.');
    }

    public static function secondaryCollidesWithPrimary(): self
    {
        return new self('Refusing to run: the secondary backup destination resolves to the same place as the primary — that is not a second copy. Fix this in Backup Manager settings before running.');
    }

    public static function encryptionMisconfigured(): self
    {
        return new self('Refusing to run: a bucket-based destination (S3/S3-compatible) is configured without an encryption password. Set one in Backup Manager settings before running.');
    }
}
