<?php

declare(strict_types=1);

namespace Magna\Plugins\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;

class PluginMakeCommand extends Command
{
    protected $signature = 'magna:plugin:make {name : The vendor/package name, e.g. acme/dating}';

    protected $description = 'Scaffold a new Magna plugin skeleton in plugins-dev/.';

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
        $this->createDirectory("{$targetDir}/schemas");
        $this->createDirectory("{$targetDir}/blocks");
        $this->createDirectory("{$targetDir}/tests");

        $this->writeFile("{$targetDir}/magna.json", $this->stubManifest($name, $namespace, $className));
        $this->writeFile("{$targetDir}/composer.json", $this->stubComposerJson($name, $namespace));
        $this->writeFile("{$targetDir}/src/{$className}.php", $this->stubEntryClass($namespace, $className));
        $this->writeFile("{$targetDir}/routes/api.php", $this->stubRoutes());
        $this->writeFile("{$targetDir}/.gitkeep", '');

        $this->addPathRepository($name, $targetDir);

        $this->info("Plugin [{$name}] scaffolded at plugins-dev/{$vendor}/{$package}.");
        $this->newLine();
        $this->line('Next steps:');
        $this->line("  1. composer require {$name} --dev");
        $this->line("  2. php artisan magna:plugin:install {$name}");

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
        $data = [
            'name' => $name,
            'description' => 'A Magna CMS plugin.',
            'type' => 'magna-plugin',
            'require' => ['php' => '^8.3'],
            'autoload' => ['psr-4' => [$namespace.'\\' => 'src/']],
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function stubEntryClass(string $namespace, string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use Magna\Plugins\Plugin;

class {$className} extends Plugin
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }

    public function enable(): void
    {
        //
    }

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

        $relPath = 'plugins-dev/'.str_replace('/', '/', $name);
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
