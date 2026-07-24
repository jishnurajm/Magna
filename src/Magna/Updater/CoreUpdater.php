<?php

declare(strict_types=1);

namespace Magna\Updater;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\OctaneServiceProvider;
use Magna\Plugins\PluginInfo;
use Magna\Plugins\PluginManager;
use Magna\Plugins\PluginRecord;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;
use ZipArchive;

/**
 * Applies a published core release: downloads the pre-built archive, overlays
 * core-owned paths, migrates, clears caches, and reloads Octane. Modeled on
 * Magna\Marketplace\PluginInstaller — same lock-and-poll shape, same
 * fail-with-message pattern, progress written to cache for the UI to read.
 *
 * Scope note: this overlays source code only (src/Magna, app, bootstrap,
 * routes, database/migrations) — never composer.json/composer.lock/vendor/.
 * A customer's vendor/ may contain plugin packages added by
 * PluginInstaller's own `composer require` calls that the core release's
 * composer.json knows nothing about; overlaying it wholesale would silently
 * drop them. A core release that changes its own Composer dependencies is
 * therefore not yet a "one-click" case — see docs/updates-architecture.md.
 *
 * Also out of scope for the same reason: a generic, cross-driver DB
 * dump/restore. Rollback restores the file-level snapshot only; if
 * `migrate --force` fails partway, the site owner's own DB backup (which
 * they should always take before updating, same as before any migration)
 * is the recovery path.
 */
class CoreUpdater
{
    private const LOCK_KEY = 'magna.updater.apply.lock';

    /** @var list<string> */
    private const CORE_OWNED_PATHS = [
        'src/Magna',
        'app',
        'bootstrap',
        'routes',
        'database/migrations',
    ];

    /**
     * `$zipUrl` comes straight from Update Manager's `/updates` response
     * (see UpdateEntry::$downloadUrl / UpdateCheckClient) with no signature
     * or checksum on the archive itself — this overlay is the single
     * highest-blast-radius operation in the app (it replaces `app/`,
     * `bootstrap/`, and `src/Magna` — i.e. the code that runs on every
     * request — on every connected install that clicks "Update Now"). A
     * compromised or MITM'd response could otherwise point this at an
     * arbitrary host. This allowlist is a floor, not a full fix: it stops
     * an attacker-supplied arbitrary host, but does not verify archive
     * integrity. That needs Update Manager to publish a signature/checksum
     * alongside the URL and this class to verify it before extracting —
     * tracked as an open item in docs/SECURITY_AUDIT.md pending that
     * server-side change.
     *
     * @var list<string>
     */
    private const ALLOWED_DOWNLOAD_HOSTS = [
        'managemagna.jrstudios.dev',
        'github.com',
        'objects.githubusercontent.com',
        'codeload.github.com',
    ];

    public function __construct(
        private readonly PluginManager $plugins,
        private readonly Filesystem $files,
    ) {}

    public function apply(string $targetVersion, string $zipUrl, bool $force = false): CoreUpdateState
    {
        $this->setProgress(CoreUpdateState::Running, 'Starting…');

        $lock = Cache::lock(self::LOCK_KEY, 1800);
        if (! $lock->get()) {
            $this->setProgress(CoreUpdateState::Queued, 'Another update is already in progress…');

            return CoreUpdateState::Queued;
        }

        $backupPath = null;

        try {
            $incompatible = $this->checkCompatibility($targetVersion);
            if ($incompatible !== [] && ! $force) {
                $names = array_map(static fn (IncompatiblePlugin $p): string => $p->displayName, $incompatible);

                return $this->fail('These enabled plugins are not compatible with v'.$targetVersion.': '.implode(', ', $names).'. Disable them first or wait for updated versions.');
            }

            $this->setProgress(CoreUpdateState::Running, 'Backing up current files…');
            $backupPath = $this->backup();

            $this->setProgress(CoreUpdateState::Running, 'Downloading release…');
            $zipPath = $this->download($zipUrl);

            $this->setProgress(CoreUpdateState::Running, 'Extracting…');
            $extractPath = $this->extract($zipPath);

            Artisan::call('down');

            $disableResult = null;

            try {
                $this->setProgress(CoreUpdateState::Running, 'Applying update…');
                $this->overlay($extractPath);

                $this->setProgress(CoreUpdateState::Running, 'Running migrations…');
                Artisan::call('migrate', ['--force' => true]);

                // A forced update may leave plugins enabled that are known-incompatible
                // with $targetVersion. Booting one on the next request could throw during
                // Laravel's own bootstrap and take the whole panel down with it — so they
                // are disabled here, still inside maintenance mode, before the site comes
                // back up. Data/config are preserved; the admin re-enables once updated.
                if ($force && $incompatible !== []) {
                    $this->setProgress(CoreUpdateState::Running, 'Disabling incompatible plugins…');
                    $disableResult = $this->disableIncompatiblePlugins($incompatible);
                }

                $this->setProgress(CoreUpdateState::Running, 'Clearing caches…');
                $this->clearCachesAndReloadOctane();
            } catch (Throwable $e) {
                $this->setProgress(CoreUpdateState::Running, 'Update failed mid-apply — restoring previous files…');
                $this->restore($backupPath);
                Artisan::call('config:clear');

                return $this->fail("Update failed and files were restored: {$e->getMessage()}. If migrations ran before the failure, your database may be ahead of the restored code — check your own DB backup before continuing.");
            } finally {
                Artisan::call('up');
            }

            $this->cleanup($zipPath, $extractPath);

            $message = $this->buildSuccessMessage($targetVersion, $disableResult);
            $this->setProgress(CoreUpdateState::Completed, $message);

            return CoreUpdateState::Completed;
        } catch (Throwable $e) {
            if ($backupPath !== null) {
                $this->restore($backupPath);
            }

            return $this->fail('Update failed before any files were changed: '.$e->getMessage());
        } finally {
            $lock->release();
        }
    }

    /**
     * Current apply progress, read the same way Marketplace\PluginInstaller::progress() is.
     *
     * @return array{state: string|null, message: string}
     */
    public static function progress(): array
    {
        $value = Cache::get(self::key());

        if (is_array($value) && isset($value['state'], $value['message']) && is_string($value['message'])) {
            $state = is_string($value['state']) ? $value['state'] : null;

            return ['state' => $state, 'message' => $value['message']];
        }

        return ['state' => null, 'message' => ''];
    }

    /**
     * Enabled plugins whose manifest compat range does not satisfy the target
     * core version. Public so the admin UI can pre-flight this before dispatching
     * the update job and offer the admin a choice; apply() re-checks it itself
     * regardless, since it cannot trust the caller ran this first.
     *
     * @return list<IncompatiblePlugin>
     */
    public function checkCompatibility(string $targetVersion): array
    {
        $enabledNames = PluginRecord::query()->where('enabled', true)->pluck('name')->all();
        if ($enabledNames === []) {
            return [];
        }

        $incompatible = [];
        foreach ($this->plugins->discover() as $info) {
            /** @var PluginInfo $info */
            if (in_array($info->manifest->name, $enabledNames, true) && ! $info->manifest->isCompatibleWith($targetVersion)) {
                $incompatible[] = new IncompatiblePlugin(
                    name: $info->manifest->name,
                    displayName: $info->manifest->displayName,
                    installedVersion: $info->manifest->version,
                    requiredCompat: $info->manifest->magnaCompat,
                );
            }
        }

        return $incompatible;
    }

    /** @param  list<IncompatiblePlugin>  $incompatible */
    private function disableIncompatiblePlugins(array $incompatible): PluginDisableResult
    {
        $disabled = [];
        $failed = [];

        foreach ($incompatible as $plugin) {
            try {
                $this->plugins->disable($plugin->name);
                $disabled[] = $plugin->displayName;
            } catch (Throwable) {
                $failed[] = $plugin->displayName;
            }
        }

        return new PluginDisableResult($disabled, $failed);
    }

    private function clearCachesAndReloadOctane(): void
    {
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        if (class_exists(OctaneServiceProvider::class) && filter_var(getenv('LARAVEL_OCTANE'), FILTER_VALIDATE_BOOLEAN)) {
            Artisan::call('octane:reload');
        }
    }

    private function buildSuccessMessage(string $targetVersion, ?PluginDisableResult $disableResult): string
    {
        $message = "Updated to v{$targetVersion}.";

        if ($disableResult === null) {
            return $message;
        }

        if ($disableResult->disabled !== []) {
            $message .= ' Automatically disabled (incompatible with this version): '.implode(', ', $disableResult->disabled).'.';
        }
        if ($disableResult->failed !== []) {
            $message .= ' WARNING: could not disable these incompatible plugins — disable them manually now: '.implode(', ', $disableResult->failed).'.';
        }

        return $message;
    }

    /** Snapshot the core-owned paths to a timestamped backup directory, for file-level rollback. */
    private function backup(): string
    {
        $backupPath = storage_path('app/magna-updates/backups/'.now()->format('Y_m_d_His'));

        foreach (self::CORE_OWNED_PATHS as $relative) {
            $source = base_path($relative);
            if (! is_dir($source) && ! is_file($source)) {
                continue;
            }
            $this->files->mirror($source, $backupPath.'/'.$relative, null, ['override' => true]);
        }

        return $backupPath;
    }

    private function restore(string $backupPath): void
    {
        foreach (self::CORE_OWNED_PATHS as $relative) {
            $source = $backupPath.'/'.$relative;
            if (! is_dir($source) && ! is_file($source)) {
                continue;
            }
            $this->files->mirror($source, base_path($relative), null, ['override' => true, 'delete' => true]);
        }
    }

    private function guardDownloadUrl(string $zipUrl): void
    {
        $scheme = parse_url($zipUrl, PHP_URL_SCHEME);
        $host = parse_url($zipUrl, PHP_URL_HOST);

        if ($scheme !== 'https' || ! is_string($host) || ! in_array(strtolower($host), self::ALLOWED_DOWNLOAD_HOSTS, true)) {
            throw new \RuntimeException("Refusing to download a core update from an untrusted source: {$zipUrl}");
        }
    }

    private function download(string $zipUrl): string
    {
        $this->guardDownloadUrl($zipUrl);

        $tmpDir = storage_path('app/magna-updates/tmp');
        $this->files->mkdir($tmpDir);
        $zipPath = $tmpDir.'/core-'.uniqid().'.zip';

        $response = Http::timeout(300)->sink($zipPath)->get($zipUrl);
        if (! $response->successful()) {
            throw new \RuntimeException("Could not download the release archive (HTTP {$response->status()}).");
        }

        return $zipPath;
    }

    private function extract(string $zipPath): string
    {
        $extractPath = storage_path('app/magna-updates/tmp/extract-'.uniqid());
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('The downloaded release archive is not a valid zip file.');
        }

        $zip->extractTo($extractPath);
        $zip->close();

        // GitHub-style archives wrap contents in a single top-level folder
        // (e.g. "Magna-1.2.0/") — descend into it if that's what we got.
        $entries = array_values(array_diff(scandir($extractPath) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir($extractPath.'/'.$entries[0])) {
            return $extractPath.'/'.$entries[0];
        }

        return $extractPath;
    }

    /** Replace only the core-owned paths — never composer.json/composer.lock/vendor/, never .env or storage/. */
    private function overlay(string $extractPath): void
    {
        foreach (self::CORE_OWNED_PATHS as $relative) {
            $source = $extractPath.'/'.$relative;
            if (! is_dir($source) && ! is_file($source)) {
                continue;
            }
            $this->files->mirror($source, base_path($relative), null, ['override' => true, 'delete' => true]);
        }
    }

    private function cleanup(string $zipPath, string $extractPath): void
    {
        $this->files->remove([$zipPath, $extractPath]);
    }

    private function fail(string $message): CoreUpdateState
    {
        $this->setProgress(CoreUpdateState::Failed, $message);

        return CoreUpdateState::Failed;
    }

    private function setProgress(CoreUpdateState $state, string $message): void
    {
        Cache::put(self::key(), ['state' => $state->value, 'message' => $message], 1800);
    }

    private static function key(): string
    {
        return 'magna.updater.apply.progress';
    }
}
