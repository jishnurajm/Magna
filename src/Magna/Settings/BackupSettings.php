<?php

declare(strict_types=1);

namespace Magna\Settings;

use Magna\Settings\Attributes\Secret;

/**
 * Backup Manager settings. Intentionally its own disk/credentials, separate
 * from StorageSettings — see docs/backup-manager-plan.md ("Decisions made up
 * front", #1). Pointing backups at the same bucket/path as the live media
 * disk is a single point of failure and is rejected by collidesWithMediaDisk()
 * before it can be saved.
 */
class BackupSettings extends Settings
{
    public bool $enabled = false;

    // Primary destination.
    public string $disk = 'local';

    public ?string $s3_key = null;

    #[Secret]
    public ?string $s3_secret = null;

    public ?string $s3_bucket = null;

    public ?string $s3_region = null;

    public ?string $s3_url = null;

    // Secondary (offsite) destination — schema reserved from Stage 2, UI
    // activated in Stage 7. Null disk means "not configured" — single-disk
    // backups remain fully supported.
    public ?string $secondary_disk = null;

    public ?string $secondary_s3_key = null;

    #[Secret]
    public ?string $secondary_s3_secret = null;

    public ?string $secondary_s3_bucket = null;

    public ?string $secondary_s3_region = null;

    public ?string $secondary_s3_url = null;

    // Stage 7: archive-level (ZipArchive AES-256, via Spatie's own
    // password/encryption config) — required whenever either destination
    // disk is bucket-based (s3/s3-like). Not required for local/public,
    // where the archive never leaves the server's own filesystem.
    #[Secret]
    public ?string $encryption_password = null;

    // Stage 7: alert-only threshold — a backup exceeding this size doesn't
    // block or fail the run, it's an early signal of runaway growth (e.g.
    // media never getting pruned). Null disables the check.
    public ?int $size_warning_mb = null;

    /** daily | weekly | custom_cron */
    public string $frequency = 'daily';

    public ?string $cron_expression = null;

    /** Time of day (H:i, app timezone) scheduled runs fire at, for daily/weekly frequency. */
    public string $run_at = '02:00';

    public int $retention_count = 7;

    public int $retention_days = 30;

    public bool $include_database = true;

    public bool $include_files = true;

    public bool $include_config = true;

    /** @var array<int, string> */
    public array $notify_emails = [];

    // Stage 8: selective exclusion — e.g. skip a huge analytics/log table
    // that doesn't need to ride along in every backup. Only applies when
    // include_database is on; empty means "dump everything," same as not
    // setting it at all.
    /** @var array<int, string> */
    public array $excluded_tables = [];

    /**
     * Whether this settings' primary destination resolves to the same
     * physical location as $storage's disk. Pure/static-shaped on purpose
     * so it's testable without touching the database — see
     * tests/Feature/Backup/BackupSettingsPageTest.php.
     */
    public function collidesWithMediaDisk(StorageSettings $storage): bool
    {
        return self::destinationKey($this->disk, $this->s3_bucket, $this->s3_region, $this->s3_url)
            === self::destinationKey($storage->disk, $storage->s3_bucket, $storage->s3_region, $storage->s3_url);
    }

    /** Same check as collidesWithMediaDisk(), for the secondary destination once it's set. */
    public function secondaryCollidesWithMediaDisk(StorageSettings $storage): bool
    {
        if ($this->secondary_disk === null) {
            return false;
        }

        return self::destinationKey($this->secondary_disk, $this->secondary_s3_bucket, $this->secondary_s3_region, $this->secondary_s3_url)
            === self::destinationKey($storage->disk, $storage->s3_bucket, $storage->s3_region, $storage->s3_url);
    }

    /**
     * A secondary that resolves to the same place as the primary isn't a
     * second copy — it's the same copy written twice, defeating the 3-2-1
     * point of having one at all.
     */
    public function secondaryCollidesWithPrimary(): bool
    {
        if ($this->secondary_disk === null) {
            return false;
        }

        return self::destinationKey($this->secondary_disk, $this->secondary_s3_bucket, $this->secondary_s3_region, $this->secondary_s3_url)
            === self::destinationKey($this->disk, $this->s3_bucket, $this->s3_region, $this->s3_url);
    }

    /** Whether either configured destination is bucket-based and therefore requires encryption_password to be set. */
    public function requiresEncryption(): bool
    {
        return in_array($this->disk, ['s3', 's3-like'], true)
            || in_array($this->secondary_disk, ['s3', 's3-like'], true);
    }

    public function encryptionMisconfigured(): bool
    {
        return $this->requiresEncryption() && blank($this->encryption_password);
    }

    /**
     * Collapses a disk config down to what actually identifies its physical
     * destination. 's3' and 's3-like' are treated as the same family since
     * both are bucket-addressed — what matters is whether the bucket/region/
     * endpoint match, not which of the two driver labels was picked.
     * 'local' and 'public' are each a single fixed root path (see
     * config/filesystems.php), so the disk name alone identifies them.
     */
    private static function destinationKey(string $disk, ?string $bucket, ?string $region, ?string $url): string
    {
        $normalizedDisk = in_array($disk, ['s3', 's3-like'], true) ? 's3' : $disk;

        return match ($normalizedDisk) {
            's3' => implode('|', ['s3', $bucket ?? '', $region ?? '', $url ?? '']),
            default => $normalizedDisk,
        };
    }
}
