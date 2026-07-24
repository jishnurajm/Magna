<?php

declare(strict_types=1);

namespace Magna\Plugins\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;

class PluginMakeCommand extends Command
{
    protected $signature = 'magna:plugin:make {name : The vendor/package name, e.g. acme/dating}';

    protected $description = 'Scaffold a new Magna plugin (SDK-based) in plugins-dev/.';

    /** Composer constraint for the SDK the scaffolded plugin builds against. */
    private const SDK_CONSTRAINT = '^1.0';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?\/[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/', $name)) {
            $this->error('Plugin name must be in vendor/package format (lowercase, hyphens/underscores allowed).');

            return self::FAILURE;
        }

        [$vendor, $package] = explode('/', $name, 2);
        $targetDir = base_path("plugins-dev/{$vendor}/{$package}");

        if (is_dir($targetDir)) {
            $this->error("Directory already exists: {$targetDir}");

            return self::FAILURE;
        }

        $namespace = Str::studly($vendor).'\\'.Str::studly($package);
        $className = Str::studly($package).'Plugin';

        $this->createDirectory($targetDir);
        $this->createDirectory("{$targetDir}/src");
        $this->createDirectory("{$targetDir}/routes");
        $this->createDirectory("{$targetDir}/database/migrations");
        $this->createDirectory("{$targetDir}/tests");

        $this->writeFile("{$targetDir}/magna.json", $this->stubManifest($name, $namespace, $className));
        $this->writeFile("{$targetDir}/composer.json", $this->stubComposerJson($name, $namespace));
        $this->writeFile("{$targetDir}/README.md", $this->stubReadme($name, $className));
        $this->writeFile("{$targetDir}/phpunit.xml.dist", $this->stubPhpunit());
        $this->writeFile("{$targetDir}/.gitignore", "/vendor/\ncomposer.lock\n.phpunit.result.cache\n.phpunit.cache/\n");
        $this->writeFile("{$targetDir}/src/{$className}.php", $this->stubEntryClass($namespace, $className));
        $this->writeFile("{$targetDir}/routes/api.php", $this->stubRoutes());
        $this->writeFile("{$targetDir}/database/migrations/.gitkeep", '');
        $this->writeFile("{$targetDir}/tests/ManifestTest.php", $this->stubManifestTest($namespace));

        $this->addPathRepository($name, $targetDir);

        $this->info("Plugin [{$name}] scaffolded at plugins-dev/{$vendor}/{$package}.");
        $this->newLine();
        $this->line('Next steps:');
        $this->line("  1. composer require {$name} --dev");
        $this->line("  2. php artisan magna:plugin:install {$name}");
        $this->line('  3. Implement a contract on the entry class (see the SDK README).');

        return self::SUCCESS;
    }

    private function createDirectory(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function writeFile(string $path, string $content): void
    {
        file_put_contents($path, $content);
        $this->line("  <fg=green>create</> {$path}");
    }

    private function stubManifest(string $name, string $namespace, string $className): string
    {
        $manifest = [
            'name' => $name,
            'displayName' => Str::title(str_replace(['-', '_'], ' ', explode('/', $name)[1])),
            'description' => 'A Magna CMS plugin.',
            'version' => '1.0.0',
            'author' => '',
            'license' => 'MIT',
            'compat' => ['magna' => '^1.0', 'php' => '^8.3'],
            'entry' => $namespace.'\\'.$className,
            'provides' => ['apiRoutes' => false, 'adminNavigation' => false],
            'permissions' => [],
            'uninstall' => ['tables' => [], 'contentTypes' => []],
        ];

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function stubComposerJson(string $name, string $namespace): string
    {
        $testNamespace = str_replace('\\\\', '\\', $namespace).'\\Tests\\';

        $data = [
            'name' => $name,
            'description' => 'A Magna CMS plugin.',
            'type' => 'magna-plugin',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.3',
                'magna-cms/plugin-sdk' => self::SDK_CONSTRAINT,
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^11.5',
            ],
            'autoload' => ['psr-4' => [$namespace.'\\' => 'src/']],
            'autoload-dev' => ['psr-4' => [$testNamespace => 'tests/']],
            'minimum-stability' => 'dev',
            'prefer-stable' => true,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function stubReadme(string $name, string $className): string
    {
        return <<<MD
        # {$name}

        A [Magna CMS](https://github.com/Magna-CMS) plugin, built on the
        [Magna Plugin SDK](https://github.com/Magna-CMS/Magna-Plugin-SDK).

        ## Develop

        ```bash
        composer install
        vendor/bin/phpunit
        ```

        ## Structure

        - `magna.json` — the plugin manifest (validated by the SDK).
        - `src/{$className}.php` — the entry class; implement SDK contracts here.
        - `routes/api.php` — API routes, auto-prefixed at `/api/v1/{plugin-slug}/`.
        - `database/migrations/` — schema the plugin owns.

        ## Publish

        Tag a release and submit the repository to Packagist with
        `"type": "magna-plugin"`. Users install it from the Magna plugin store.
        MD."\n";
    }

    private function stubPhpunit(): string
    {
        return <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                 xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
                 bootstrap="vendor/autoload.php"
                 colors="true">
            <testsuites>
                <testsuite name="Plugin">
                    <directory>tests</directory>
                </testsuite>
            </testsuites>
        </phpunit>
        XML."\n";
    }

    private function stubEntryClass(string $namespace, string $className): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$namespace};

        use Magna\\Plugins\\Plugin;

        // Implement any SDK contract to hook into the CMS, e.g.:
        //   use Magna\\Contracts\\RegistersDashboardWidgets;
        //   class {$className} extends Plugin implements RegistersDashboardWidgets
        class {$className} extends Plugin
        {
            /** Bind services into the container. */
            public function register(): void
            {
                //
            }

            /** Load routes, views, listeners, etc. */
            public function boot(): void
            {
                //
            }

            /** One-time setup when an admin enables the plugin. */
            public function enable(): void
            {
                //
            }

            /** One-time teardown when an admin disables the plugin. */
            public function disable(): void
            {
                //
            }
        }
        PHP;
    }

    private function stubRoutes(): string
    {
        return <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        // Plugin API routes — auto-prefixed at /api/v1/{plugin-slug}/ by the PluginManager.
        // Apply 'magna.api' middleware to require a bearer token.
        //
        // Route::middleware('magna.api')->get('/example', fn () => response()->json(['ok' => true]));
        PHP;
    }

    private function stubManifestTest(string $namespace): string
    {
        $testNamespace = $namespace.'\\Tests';

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$testNamespace};

        use Magna\\Plugins\\Manifest;
        use PHPUnit\\Framework\\TestCase;

        /**
         * Validates this plugin's magna.json against the Magna Plugin SDK. Runs
         * standalone (composer install && vendor/bin/phpunit) — no CMS required.
         */
        final class ManifestTest extends TestCase
        {
            public function test_manifest_is_valid(): void
            {
                \$manifest = Manifest::loadFromFile(__DIR__.'/../magna.json');

                \$this->assertNotSame('', \$manifest->name);
                \$this->assertNotSame('', \$manifest->entryClass);
                \$this->assertTrue(\$manifest->isCompatibleWith('1.0.0'));
            }
        }
        PHP;
    }

    private function addPathRepository(string $name, string $targetDir): void
    {
        $composerPath = base_path('composer.json');
        $raw = file_get_contents($composerPath);
        if ($raw === false) {
            $this->warn('Could not read composer.json — add the path repository manually.');

            return;
        }

        try {
            /** @var array<string, mixed> $composer */
            $composer = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->warn('Could not parse composer.json — add the path repository manually.');

            return;
        }

        /** @var list<array<string, string>> $repos */
        $repos = is_array($composer['repositories'] ?? null) ? $composer['repositories'] : [];

        $relPath = 'plugins-dev/'.$name;
        foreach ($repos as $repo) {
            if (($repo['url'] ?? '') === $relPath) {
                return; // Already registered.
            }
        }

        $repos[] = ['type' => 'path', 'url' => $relPath];
        $composer['repositories'] = $repos;

        $encoded = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        file_put_contents($composerPath, $encoded);
        $this->line("  <fg=green>update</> composer.json (added path repository for {$name})");
    }
}
