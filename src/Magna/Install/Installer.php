<?php

declare(strict_types=1);

namespace Magna\Install;

use Magna\MagnaServiceProvider;
use RuntimeException;

final class Installer
{
    public static function isInstalled(): bool
    {
        $override = config('magna.installed_override');

        if ($override !== null) {
            return filter_var($override, FILTER_VALIDATE_BOOL);
        }

        return is_file(self::lockPath());
    }

    public static function markInstalled(): void
    {
        $payload = json_encode([
            'version' => MagnaServiceProvider::VERSION,
            'installed_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT);

        $path = self::lockPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        if (file_put_contents($path, $payload) === false) {
            throw new RuntimeException("Unable to write installation lock at [{$path}].");
        }
    }

    private static function lockPath(): string
    {
        $path = config('magna.install.lock_path');

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('magna.install.lock_path is not configured.');
        }

        return $path;
    }
}
