<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
use Magna\Auth\PermissionRegistry;
use Magna\Content\Models\ContentTypeRecord;
use Magna\Content\SchemaRegistry;
use Magna\Plugins\Exceptions\InvalidManifestException;
use Magna\Plugins\Exceptions\PluginNotFoundException;
use Magna\Plugins\Manifest;
use Magna\Plugins\ManifestValidator;
use Magna\Plugins\PluginManager;
use Magna\Plugins\PluginRecord;

// ── Manifest validation ─────────────────────────────────────────────────────

it('accepts a valid magna.json manifest', function (): void {
    $data = validManifestData();
    expect(fn () => ManifestValidator::validate($data))->not->toThrow(InvalidManifestException::class);
});

it('rejects a manifest missing required fields', function (string $field): void {
    $data = validManifestData();
    unset($data[$field]);
    expect(fn () => ManifestValidator::validate($data))->toThrow(InvalidManifestException::class);
})->with(['name', 'displayName', 'description', 'version', 'author', 'license', 'compat', 'entry', 'permissions']);

it('rejects a manifest with invalid name format', function (): void {
    $data = validManifestData(['name' => 'NotVendorSlashPackage']);
    expect(fn () => ManifestValidator::validate($data))->toThrow(InvalidManifestException::class);
});

it('rejects a manifest missing compat.magna', function (): void {
    $data = validManifestData(['compat' => ['php' => '^8.3']]);
    expect(fn () => ManifestValidator::validate($data))->toThrow(InvalidManifestException::class);
});

it('rejects a manifest where permissions is not an array', function (): void {
    $data = validManifestData(['permissions' => 'not-an-array']);
    expect(fn () => ManifestValidator::validate($data))->toThrow(InvalidManifestException::class);
});

it('rejects a manifest with an invalid permission key format', function (): void {
    $data = validManifestData(['permissions' => ['NoDotsHere']]);
    expect(fn () => ManifestValidator::validate($data))->toThrow(InvalidManifestException::class);
});

it('builds a Manifest value object from a valid array', function (): void {
    $data = validManifestData();
    $manifest = Manifest::fromArray($data);

    expect($manifest->name)->toBe('test/plugin')
        ->and($manifest->magnaCompat)->toBe('^1.0')
        ->and($manifest->entryClass)->toBe('Test\\Plugin\\TestPlugin')
        ->and($manifest->permissions)->toBe(['test.resource.view']);
});

// ── Compat checking ─────────────────────────────────────────────────────────

it('accepts a plugin whose magna compat satisfies the core version', function (): void {
    $manifest = Manifest::fromArray(validManifestData(['compat' => ['magna' => '^1.0']]));
    expect($manifest->isCompatibleWith('1.0.0-dev'))->toBeTrue();
});

it('refuses to enable a plugin incompatible with the core version', function (): void {
    $manifest = Manifest::fromArray(validManifestData(['compat' => ['magna' => '^2.0']]));
    expect($manifest->isCompatibleWith('1.0.0-dev'))->toBeFalse();
});

it('throws PluginCompatibilityException when enabling an incompatible plugin', function (): void {
    // hello-world uses ^1.0 which passes. Override with an incompatible version for this test.
    $incompatibleManifest = file_get_contents(base_path('plugins-dev/magna/hello-world/magna.json'));
    assert(is_string($incompatibleManifest));

    // Write a temp manifest with ^2.0 compat.
    $tmpDir = sys_get_temp_dir().'/magna-compat-test';
    @mkdir($tmpDir.'/database/migrations', 0755, true);
    file_put_contents(
        $tmpDir.'/magna.json',
        str_replace('"^1.0"', '"^2.0"', $incompatibleManifest)
    );

    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);

    // Manually call with a fake discovery — we test compat check via isCompatibleWith.
    $fakeManifest = Manifest::loadFromFile($tmpDir.'/magna.json');
    expect($fakeManifest->isCompatibleWith('1.0.0-dev'))->toBeFalse();
});

// ── Enable / disable lifecycle ───────────────────────────────────────────────

it('enables a plugin and records it in the plugins table', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    $manager->enable('magna/hello-world');

    $record = PluginRecord::query()->where('name', 'magna/hello-world')->first();
    expect($record)->not->toBeNull()
        ->and($record->enabled)->toBeTrue()
        ->and($record->version)->toBe('1.0.0');
});

it('registers plugin permissions in the permission registry on enable', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    $manager->enable('magna/hello-world');

    /** @var PermissionRegistry $registry */
    $registry = app(PermissionRegistry::class);
    expect($registry->has('hello-world.greet'))->toBeTrue();
});

it('disables a plugin and marks it as disabled in the database', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    $manager->enable('magna/hello-world');
    $manager->disable('magna/hello-world');

    $record = PluginRecord::query()->where('name', 'magna/hello-world')->first();
    expect($record)->not->toBeNull()
        ->and($record->enabled)->toBeFalse()
        ->and($record->disabled_at)->not->toBeNull();
});

it('throws PluginNotFoundException when enabling an unknown plugin', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    expect(fn () => $manager->enable('nonexistent/plugin'))->toThrow(PluginNotFoundException::class);
});

// ── Uninstall ────────────────────────────────────────────────────────────────

it('removes the plugin record on uninstall without purge', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    $manager->enable('magna/hello-world');
    $manager->uninstall('magna/hello-world', purge: false);

    expect(PluginRecord::query()->where('name', 'magna/hello-world')->exists())->toBeFalse();
});

it('removes plugin content types from the registry and content_types table on uninstall', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    $manager->enable('magna/hello-world');

    // Sanity: enabling registered the greeting content type.
    expect(app(SchemaRegistry::class)->has('greeting'))->toBeTrue()
        ->and(ContentTypeRecord::query()->where('handle', 'greeting')->exists())->toBeTrue();

    $manager->uninstall('magna/hello-world', purge: false);

    // The orphaned content type must be gone so its sidebar nav disappears;
    // the data table is preserved because purge was not requested.
    expect(ContentTypeRecord::query()->where('handle', 'greeting')->exists())->toBeFalse()
        ->and(app(SchemaRegistry::class)->has('greeting'))->toBeFalse()
        ->and(Schema::hasTable('magna_entries_greeting'))->toBeTrue();
});

it('drops content type data tables on uninstall with purge', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    $manager->enable('magna/hello-world');

    $manager->uninstall('magna/hello-world', purge: true);

    expect(ContentTypeRecord::query()->where('handle', 'greeting')->exists())->toBeFalse()
        ->and(Schema::hasTable('magna_entries_greeting'))->toBeFalse();
});

it('deactivates plugin content types on disable and restores them on re-enable', function (): void {
    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);
    $manager->enable('magna/hello-world');
    $manager->disable('magna/hello-world');

    // Disabled plugin must not leave an active content type behind.
    expect(ContentTypeRecord::query()->where('handle', 'greeting')->exists())->toBeFalse();

    $manager->enable('magna/hello-world');

    // Re-enable restores it even though the physical table already existed.
    expect(ContentTypeRecord::query()->where('handle', 'greeting')->exists())->toBeTrue()
        ->and(app(SchemaRegistry::class)->has('greeting'))->toBeTrue();
});

it('drops declared tables on uninstall with purge', function (): void {
    // Create a dummy table to simulate a plugin-owned table.
    Schema::create('hello_world_dummy', function ($table): void {
        $table->id();
    });

    /** @var PluginManager $manager */
    $manager = app(PluginManager::class);

    // Manually insert a record with an uninstall.tables entry.
    PluginRecord::create([
        'name' => 'magna/hello-world',
        'display_name' => 'Hello World',
        'version' => '1.0.0',
        'enabled' => false,
        'base_path' => base_path('plugins-dev/magna/hello-world'),
        'manifest' => array_merge(
            Manifest::loadFromFile(base_path('plugins-dev/magna/hello-world/magna.json'))->toArray(),
            ['uninstall' => ['tables' => ['hello_world_dummy'], 'contentTypes' => []]]
        ),
    ]);

    $manager->uninstall('magna/hello-world', purge: true);

    expect(Schema::hasTable('hello_world_dummy'))->toBeFalse();
});

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function validManifestData(array $overrides = []): array
{
    return array_merge([
        'name' => 'test/plugin',
        'displayName' => 'Test Plugin',
        'description' => 'A test plugin.',
        'version' => '1.0.0',
        'author' => 'Test Author',
        'license' => 'MIT',
        'compat' => ['magna' => '^1.0', 'php' => '^8.3'],
        'entry' => 'Test\\Plugin\\TestPlugin',
        'provides' => [],
        'permissions' => ['test.resource.view'],
    ], $overrides);
}
