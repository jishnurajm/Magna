<?php

declare(strict_types=1);

namespace Magna\Install;

use Illuminate\Http\Request;

/**
 * Environment checks shown on the installer's first screen. Required items
 * block installation; recommended items only inform the user.
 */
final class Requirements
{
    /**
     * @return list<Requirement>
     */
    public function check(?Request $request = null): array
    {
        $checks = [];

        // version_compare instead of PHP_VERSION_ID: a zip distribution can
        // land on a PHP older than composer.json ever allowed.
        $phpVersion = (string) phpversion();
        $checks[] = new Requirement(
            'php',
            'PHP 8.3 or newer',
            version_compare($phpVersion, '8.3.0', '>='),
            required: true,
            help: "Running PHP {$phpVersion}. Magna requires PHP 8.3+.",
        );

        foreach ([
            'pdo' => 'Database connectivity (PDO)',
            'mbstring' => 'Multibyte strings (mbstring)',
            'openssl' => 'Encryption (OpenSSL)',
            'ctype' => 'Character type checks (ctype)',
            'fileinfo' => 'File type detection (fileinfo)',
            'curl' => 'HTTP client (cURL)',
            'dom' => 'XML/DOM processing',
            'tokenizer' => 'PHP tokenizer',
        ] as $extension => $label) {
            $checks[] = new Requirement(
                'ext-'.$extension,
                $label,
                extension_loaded($extension),
                required: true,
                help: "PHP extension \"{$extension}\" must be installed and enabled.",
            );
        }

        $checks[] = new Requirement(
            'writable-storage',
            'storage/ directory is writable',
            is_writable(storage_path()),
            required: true,
            help: 'Magna writes caches, logs, and uploads here.',
        );

        $checks[] = new Requirement(
            'writable-bootstrap',
            'bootstrap/cache/ directory is writable',
            is_writable(base_path('bootstrap/cache')),
            required: true,
            help: 'Laravel writes framework caches here.',
        );

        $envPath = config()->string('magna.install.env_path', base_path('.env'));
        $checks[] = new Requirement(
            'writable-env',
            'Environment file (.env) is writable',
            is_file($envPath) ? is_writable($envPath) : is_writable(dirname($envPath)),
            required: true,
            help: 'The installer stores your configuration in this file.',
        );

        $checks[] = new Requirement(
            'argon2id',
            'Argon2id password hashing',
            defined('PASSWORD_ARGON2ID'),
            required: false,
            help: 'Recommended for strongest password storage. Without it, Magna falls back to bcrypt (still secure).',
        );

        foreach ([
            'intl' => ['Internationalization (intl)', 'Needed for advanced localization features.'],
            'gd' => ['Image processing (GD)', 'Needed for image conversions and thumbnails.'],
            'zip' => ['Archive handling (zip)', 'Used by plugin tooling.'],
        ] as $extension => [$label, $help]) {
            $checks[] = new Requirement(
                'ext-'.$extension,
                $label,
                extension_loaded($extension),
                required: false,
                help: $help,
            );
        }

        $checks[] = new Requirement(
            'https',
            'Served over HTTPS',
            $request?->isSecure() ?? false,
            required: false,
            help: 'Strongly recommended for any site that is reachable from the internet.',
        );

        $checks[] = new Requirement(
            'redis',
            'Redis available',
            extension_loaded('redis') || class_exists('Predis\Client'),
            required: false,
            help: 'Optional. Improves cache and queue performance in production.',
        );

        return $checks;
    }

    /**
     * @param  list<Requirement>  $checks
     */
    public function requiredPass(array $checks): bool
    {
        foreach ($checks as $check) {
            if ($check->required && ! $check->passed) {
                return false;
            }
        }

        return true;
    }
}
