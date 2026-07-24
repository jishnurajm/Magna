<?php

declare(strict_types=1);

namespace Magna\Backup;

use Illuminate\Support\Facades\Config as ConfigFacade;
use Illuminate\Support\Facades\Storage;
use Magna\Backup\Exceptions\BackupConfigurationException;
use Magna\Settings\BackupSettings;
use Magna\Settings\ContentSettings;
use Magna\Settings\GeneralSettings;
use Magna\Settings\LocalizationSettings;
use Magna\Settings\MailSettings;
use Magna\Settings\MediaSettings;
use Magna\Settings\PerformanceSettings;
use Magna\Settings\SecuritySettings;
use Magna\Settings\Settings;
use Magna\Settings\StorageSettings;
use Magna\Settings\UrlSettings;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Notifications\EventHandler;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;

/**
 * Wraps spatie/laravel-backup's engine, driven entirely by BackupSettings
 * instead of config/backup.php. See docs/backup-manager-plan.md, Stage 3.
 *
 * Two things this adds on top of Spatie: a settings-export step (Spatie has
 * no concept of Magna's Settings table), and a runtime re-check of the
 * Decision #1 destination-collision guard — BackupSettingsPage::save()
 * already blocks saving a colliding destination, but StorageSettings can be
 * changed independently *after* a valid BackupSettings was saved, so a
 * scheduled/manual run re-checks at run time rather than trusting that the
 * save-time check is still true.
 */
class BackupService
{
    private const PRIMARY_DISK_NAME = 'magna_backup_primary';

    private const SECONDARY_DISK_NAME = 'magna_backup_secondary';

    private const SETTINGS_EXPORT_DIR = 'magna-config-export';

    private const SETTINGS_EXPORT_FILENAME = 'magna-settings-export.json';

    public function run(BackupSettings $settings, ?string $filename = null): BackupResult
    {
        if (! $settings->include_database && ! $settings->include_files && ! $settings->include_config) {
            throw BackupConfigurationException::nothingSelected();
        }

        if ($settings->collidesWithMediaDisk(StorageSettings::get())) {
            throw BackupConfigurationException::collidesWithMediaDisk();
        }

        if ($settings->secondaryCollidesWithMediaDisk(StorageSettings::get())) {
            throw BackupConfigurationException::secondaryCollidesWithMediaDisk();
        }

        if ($settings->secondaryCollidesWithPrimary()) {
            throw BackupConfigurationException::secondaryCollidesWithPrimary();
        }

        if ($settings->encryptionMisconfigured()) {
            throw BackupConfigurationException::encryptionMisconfigured();
        }

        // Magna has its own notification pipeline (Stage 6) — don't let
        // Spatie's own mail notifications fire against its placeholder
        // config in the meantime.
        EventHandler::disable();

        $filename ??= now()->format('Y_m_d_His').'.zip';

        $diskName = $this->resolveDisk(
            self::PRIMARY_DISK_NAME,
            $settings->disk,
            $settings->s3_key,
            $settings->s3_secret,
            $settings->s3_bucket,
            $settings->s3_region,
            $settings->s3_url,
        );

        $secondaryDiskName = $settings->secondary_disk !== null
            ? $this->resolveDisk(
                self::SECONDARY_DISK_NAME,
                $settings->secondary_disk,
                $settings->secondary_s3_key,
                $settings->secondary_s3_secret,
                $settings->secondary_s3_bucket,
                $settings->secondary_s3_region,
                $settings->secondary_s3_url,
            )
            : null;

        $settingsExportPath = $settings->include_config ? $this->exportSettings() : null;

        $this->configureSpatie($settings, $diskName, $secondaryDiskName, $settingsExportPath);

        $job = BackupJobFactory::createFromConfig(Config::fromArray(ConfigFacade::array('backup')));
        $job->setFilename($filename);
        // Runs inside a queued job (Stage 4), not a foreground console
        // command — no Ctrl+C to catch. Also required on Windows, which has
        // no pcntl/SIGINT.
        $job->disableSignals();
        $job->run();

        return $this->buildResult($diskName, $secondaryDiskName, $filename);
    }

    /**
     * 'local'/'public' are Laravel's own disks (the same ones StorageSettings
     * points at) — reused directly so the Decision #1 collision check stays
     * physically true. 's3'/'s3-like' get a dedicated runtime disk built
     * from the given credentials, independent of the app's default 's3' disk
     * (which belongs to StorageSettings, not this settings group). Shared
     * by both the primary and secondary destination — same shape, different
     * field set and runtime disk name.
     */
    private function resolveDisk(
        string $runtimeDiskName,
        string $diskLabel,
        ?string $key,
        ?string $secret,
        ?string $bucket,
        ?string $region,
        ?string $url,
    ): string {
        if (in_array($diskLabel, ['local', 'public'], true)) {
            return $diskLabel;
        }

        config(["filesystems.disks.{$runtimeDiskName}" => [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => $region ?: 'us-east-1',
            'bucket' => $bucket,
            'url' => $url,
            'endpoint' => $url,
            'use_path_style_endpoint' => $diskLabel === 's3-like',
            'throw' => true,
        ]]);

        return $runtimeDiskName;
    }

    private function configureSpatie(BackupSettings $settings, string $diskName, ?string $secondaryDiskName, ?string $settingsExportPath): void
    {
        $include = [];

        if ($settings->include_files) {
            $include[] = base_path();
        }

        // Only add explicitly when the base_path() sweep above isn't already
        // going to walk over it (it's a file under storage/app) — otherwise
        // it ends up in the zip twice.
        if ($settingsExportPath !== null && ! $settings->include_files) {
            $include[] = $settingsExportPath;
        }

        $disks = $secondaryDiskName !== null ? [$diskName, $secondaryDiskName] : [$diskName];

        // Stage 8: selective per-domain exclusion — read by
        // DbDumperFactory::createFromConnection() via
        // processExtraDumpParameters(), which turns this into a call to the
        // dumper's own excludeTables() method. Always set explicitly (even
        // to []), same reasoning as the encryption keys below: config()
        // mutations persist for the life of a queue worker/Octane process.
        $connection = ConfigFacade::string('database.default');
        config(["database.connections.{$connection}.dump.exclude_tables" => $settings->excluded_tables]);

        config([
            // BackupJob excludes its own temp dir and any local backup
            // destination directory automatically (see BackupJob::
            // directoriesUsedByBackupJob()) — no need to hand-exclude those.
            'backup.backup.source.files.include' => $include,
            'backup.backup.source.files.exclude' => [
                base_path('vendor'),
                base_path('node_modules'),
                storage_path('framework'),
            ],
            // Clean relative zip entry names (e.g. "storage/app/..." instead
            // of the full absolute path) for anything under the app root.
            'backup.backup.source.files.relative_path' => base_path(),
            'backup.backup.source.databases' => $settings->include_database ? [$connection] : [],
            // Multi-disk is native to Spatie — BackupJob writes the same
            // archive to every disk listed here in one pass.
            'backup.backup.destination.disks' => $disks,
            // Always set both keys explicitly, not just when a password is
            // present — config() mutations persist for the life of the PHP
            // process (long-running queue workers, Octane), so a prior run's
            // password must never silently leak into a run that didn't ask
            // for encryption.
            'backup.backup.password' => $settings->encryption_password,
            'backup.backup.encryption' => $settings->encryption_password !== null ? 'aes256' : 'none',
        ]);
    }

    /**
     * Exports every Settings group to JSON, secrets masked (never embed a
     * decrypted secret in a downloadable archive — see the plan doc's
     * Stage 3 notes). Hardcoded list, same pattern as
     * Magna\Management\Controllers\SettingController and
     * Magna\Admin\Pages\SettingsPage::mount() — this app has no Settings
     * registry, so a new Settings subclass needs a line added here too.
     */
    private function exportSettings(): string
    {
        /** @var array<string, Settings> $groups */
        $groups = [
            'general' => GeneralSettings::get(),
            'localization' => LocalizationSettings::get(),
            'content' => ContentSettings::get(),
            'media' => MediaSettings::get(),
            'mail' => MailSettings::get(),
            'storage' => StorageSettings::get(),
            'url' => UrlSettings::get(),
            'security' => SecuritySettings::get(),
            'performance' => PerformanceSettings::get(),
            'backup' => BackupSettings::get(),
        ];

        $export = array_map(
            static fn (Settings $settings): array => $settings->toArray(maskSecrets: true),
            $groups,
        );

        $dir = storage_path('app/'.self::SETTINGS_EXPORT_DIR);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir.DIRECTORY_SEPARATOR.self::SETTINGS_EXPORT_FILENAME;
        file_put_contents($path, json_encode($export, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }

    private function buildResult(string $diskName, ?string $secondaryDiskName, string $filename): BackupResult
    {
        $backupName = ConfigFacade::string('backup.backup.name');
        $relativePath = $backupName.'/'.$filename;

        return new BackupResult(
            disk: $diskName,
            path: $relativePath,
            sizeBytes: (int) Storage::disk($diskName)->size($relativePath),
            // Same filename/relative path on both disks — Spatie writes the
            // identical archive to every destination it's given.
            secondaryDisk: $secondaryDiskName,
            secondaryPath: $secondaryDiskName !== null ? $relativePath : null,
        );
    }
}
