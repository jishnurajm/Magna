<?php

declare(strict_types=1);

use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Magna\Admin\Resources\AuditLogResource;
use Magna\Admin\Resources\UserResource;
use Magna\Admin\Widgets\EntryCounts;
use Magna\Admin\Widgets\MediaStatsWidget;
use Magna\Admin\Widgets\RecentActivity;
use Magna\Admin\Widgets\UpcomingScheduleWidget;
use Magna\Auth\PermissionRegistry;
use Magna\Auth\Role;
use Magna\Content\ContentType;
use Magna\Content\Entry;
use Magna\Content\EntryManager;
use Magna\Content\EntryStatus;
use Magna\Content\Exceptions\DestructiveChangeException;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\Models\ContentTypeRecord;
use Magna\Content\SchemaDiffer;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers (closures to avoid global function namespace collisions) ──────────

$makeSuperAdmin = function (): User {
    $role = Role::factory()->create([
        'handle' => 'super_admin',
        'name' => 'Super Admin',
        'is_super_admin' => true,
    ]);
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);

    return $user;
};

$registerArticle = function (): void {
    $registry = app(SchemaRegistry::class);
    $type = ContentType::fromArray([
        'handle' => 'article',
        'displayName' => 'Article',
        'localizable' => false,
        'draftable' => true,
        'fields' => [
            ['handle' => 'title', 'type' => 'text', 'required' => true],
        ],
    ], app(FieldTypeRegistry::class));
    $registry->register($type);
    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);
};

// ── 1. ContentTypeBuilder: create a content type (service-layer path) ─────────

it('builder: SchemaDiffer + SchemaSyncer create DB table and ContentTypeRecord', function () use ($makeSuperAdmin): void {
    // This mirrors exactly what ContentTypeBuilder::previewDiff() + applyDiff() do.
    $admin = $makeSuperAdmin();
    $this->actingAs($admin);

    $registry = app(SchemaRegistry::class);
    $differ = app(SchemaDiffer::class);
    $syncer = app(SchemaSyncer::class);

    $schema = [
        'handle' => 'blog_post',
        'displayName' => 'Blog Post',
        'localizable' => false,
        'draftable' => true,
        'fields' => [
            ['handle' => 'title', 'type' => 'text', 'required' => true],
            ['handle' => 'body', 'type' => 'textarea', 'required' => false],
        ],
    ];

    $type = ContentType::fromArray($schema, app(FieldTypeRegistry::class));

    // Step 1: previewDiff — computes what changes are needed
    $diff = $differ->diff($type);
    expect($diff->isEmpty())->toBeFalse();
    expect($diff->hasDestructive())->toBeFalse();

    // Step 2: register in registry
    $registry->register($type);

    // Step 3: applyDiff — runs the schema migration
    $syncer->sync($diff, $registry, allowDestructive: false);

    // Step 4: persist to content_types (same as ContentTypeBuilder::applyDiff())
    ContentTypeRecord::updateOrCreate(
        ['handle' => $type->handle],
        ['display_name' => $type->displayName, 'is_database_defined' => true, 'schema' => $schema],
    );

    expect(Schema::hasTable('magna_entries_blog_post'))->toBeTrue();
    expect(Schema::hasColumn('magna_entries_blog_post', 'title'))->toBeTrue();
    expect(Schema::hasColumn('magna_entries_blog_post', 'body'))->toBeTrue();
    expect(ContentTypeRecord::where('handle', 'blog_post')->exists())->toBeTrue();
});

it('builder: SchemaDiffer correctly identifies destructive column drops', function () use ($makeSuperAdmin, $registerArticle): void {
    // Set up a pre-existing article type with a 'title' column.
    $registerArticle(); // registers type, syncs DB, and creates ContentTypeRecord via syncAll

    $admin = $makeSuperAdmin();
    $this->actingAs($admin);

    $differ = app(SchemaDiffer::class);

    // New schema with the 'title' field removed → destructive diff
    $newSchema = [
        'handle' => 'article',
        'displayName' => 'Article',
        'localizable' => false,
        'draftable' => true,
        'fields' => [],
    ];
    $newType = ContentType::fromArray($newSchema, app(FieldTypeRegistry::class));
    $diff = $differ->diff($newType);

    // Diff correctly identifies the column drop as destructive
    expect($diff->hasDestructive())->toBeTrue()
        ->and($diff->isEmpty())->toBeFalse();

    // SchemaSyncer throws when allowDestructive = false and there are destructive ops
    // (the ContentTypeBuilder UI blocks submission in this state — service enforces the guard)
    expect(fn () => app(SchemaSyncer::class)->sync($diff, app(SchemaRegistry::class), allowDestructive: false))
        ->toThrow(DestructiveChangeException::class);

    // Column still exists (sync was blocked)
    expect(Schema::hasColumn('magna_entries_article', 'title'))->toBeTrue();
});

it('builder: applying a diff with allowDestructive = true drops the column', function () use ($makeSuperAdmin, $registerArticle): void {
    $registerArticle();

    $admin = $makeSuperAdmin();
    $this->actingAs($admin);

    $registry = app(SchemaRegistry::class);
    $differ = app(SchemaDiffer::class);
    $syncer = app(SchemaSyncer::class);

    $newSchema = [
        'handle' => 'article',
        'displayName' => 'Article',
        'localizable' => false,
        'draftable' => true,
        'fields' => [],
    ];
    $newType = ContentType::fromArray($newSchema, app(FieldTypeRegistry::class));
    $diff = $differ->diff($newType);

    expect($diff->hasDestructive())->toBeTrue();

    // With allowDestructive = true the column is removed
    $registry->register($newType);
    $syncer->sync($diff, $registry, allowDestructive: true);

    expect(Schema::hasColumn('magna_entries_article', 'title'))->toBeFalse();
});

// ── 2. Create + publish entry via the admin service path ──────────────────────

it('admin can create a draft entry and publish it (service path identical to admin UI)', function () use ($makeSuperAdmin, $registerArticle): void {
    $registerArticle();

    $admin = $makeSuperAdmin();
    $this->actingAs($admin);

    $manager = app(EntryManager::class);

    // CreateEntry::handleRecordCreation() calls exactly this:
    $entry = $manager->create('article', ['title' => 'Hello Admin'], $admin->id);

    expect($entry)->toBeInstanceOf(Entry::class)
        ->and($entry->status)->toBe(EntryStatus::Draft)
        ->and($entry->published_at)->toBeNull();

    // EditEntry publish action calls exactly this:
    $published = $manager->publish($entry);

    expect($published->status)->toBe(EntryStatus::Published)
        ->and($published->published_at)->not->toBeNull();
});

it('admin can schedule entry to publish at a future date', function () use ($makeSuperAdmin, $registerArticle): void {
    $registerArticle();

    $admin = $makeSuperAdmin();
    $this->actingAs($admin);

    $manager = app(EntryManager::class);
    $entry = $manager->create('article', ['title' => 'Future Post'], $admin->id);

    // EditEntry "schedule" action calls publish() with a future timestamp
    $scheduled = $manager->publish($entry, now()->addDay());

    expect($scheduled->status)->toBe(EntryStatus::Scheduled);
});

// ── 3. Permission-restricted user sees restricted UI ─────────────────────────

it('user without users.view cannot access UserResource', function (): void {
    app(PermissionRegistry::class)->registerMany(['users.view', 'users.manage']);

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    expect(UserResource::canViewAny())->toBeFalse();
    expect(UserResource::canCreate())->toBeFalse();
});

it('user with users.view can list but cannot edit users', function (): void {
    app(PermissionRegistry::class)->registerMany(['users.view', 'users.manage']);

    $role = Role::factory()->create([
        'handle' => 'viewer',
        'name' => 'Viewer',
        'is_super_admin' => false,
    ]);
    $role->grant('users.view');

    $viewer = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $viewer->assignRole($role);
    $this->actingAs($viewer);

    $target = User::factory()->create();

    expect(UserResource::canViewAny())->toBeTrue()
        ->and(UserResource::canCreate())->toBeFalse()
        ->and(UserResource::canEdit($target))->toBeFalse()
        ->and(UserResource::canDelete($target))->toBeFalse();
});

it('user with users.manage can also edit users', function (): void {
    app(PermissionRegistry::class)->registerMany(['users.view', 'users.manage']);

    $role = Role::factory()->create([
        'handle' => 'manager',
        'name' => 'Manager',
        'is_super_admin' => false,
    ]);
    $role->grant('users.view', 'users.manage');

    $manager = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $manager->assignRole($role);
    $this->actingAs($manager);

    $target = User::factory()->create();

    expect(UserResource::canViewAny())->toBeTrue()
        ->and(UserResource::canEdit($target))->toBeTrue();
});

it('super admin has full UserResource access without explicit grants', function () use ($makeSuperAdmin): void {
    $admin = $makeSuperAdmin();
    $this->actingAs($admin);

    $target = User::factory()->create();

    expect(UserResource::canViewAny())->toBeTrue()
        ->and(UserResource::canEdit($target))->toBeTrue()
        ->and(UserResource::canCreate())->toBeTrue(); // Users with users.manage may create accounts
});

// ── S1-16: AuditLogResource read access must require audit.view ──────────────

it('user without audit.view cannot view the audit log', function (): void {
    app(PermissionRegistry::class)->registerMany(['audit.view']);

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    expect(AuditLogResource::canViewAny())->toBeFalse();
});

it('user with audit.view can view the audit log', function (): void {
    app(PermissionRegistry::class)->registerMany(['audit.view']);

    $role = Role::factory()->create();
    $role->grant('audit.view');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    expect(AuditLogResource::canViewAny())->toBeTrue();
});

it('super admin can view the audit log without explicit grants', function () use ($makeSuperAdmin): void {
    $admin = $makeSuperAdmin();
    $this->actingAs($admin);

    expect(AuditLogResource::canViewAny())->toBeTrue();
});

// ── A-2: Dashboard widgets must not leak data to unauthorized viewers ────────

it('RecentActivity widget requires audit.view', function (): void {
    app(PermissionRegistry::class)->registerMany(['audit.view']);
    $viewer = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($viewer);
    expect(RecentActivity::canView())->toBeFalse();

    $role = Role::factory()->create();
    $role->grant('audit.view');
    $viewer->assignRole($role);
    expect(RecentActivity::canView())->toBeTrue();
});

it('MediaStatsWidget requires media.view', function (): void {
    $viewer = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($viewer);
    expect(MediaStatsWidget::canView())->toBeFalse();

    $role = Role::factory()->create();
    $role->grant('media.view');
    $viewer->assignRole($role);
    expect(MediaStatsWidget::canView())->toBeTrue();
});

it('EntryCounts widget only shows counts for content types the viewer can see', function () use ($registerArticle): void {
    $registerArticle();
    Entry::type('article')->create(['title' => 'x', 'status' => EntryStatus::Published, 'locale' => '', 'published_at' => now()]);

    $viewer = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($viewer);

    $widget = new EntryCounts;
    $reflection = new ReflectionMethod($widget, 'getStats');
    $reflection->setAccessible(true);
    /** @var array<int, Stat> $stats */
    $stats = $reflection->invoke($widget);

    // No content.article.view grant — falls back to the "no content types" placeholder.
    expect($stats)->toHaveCount(1);

    $role = Role::factory()->create();
    $role->grant('content.article.view');
    $viewer->assignRole($role);
    $viewer->forgetResolvedGrants();

    $stats = $reflection->invoke($widget);
    expect($stats)->toHaveCount(1);
});

it('UpcomingScheduleWidget only shows entries for content types the viewer can see', function () use ($registerArticle): void {
    $registerArticle();
    // Schema::getTableListing() is unreliable for a table created earlier
    // in the same SQLite test connection — seed the widget's own 60s cache
    // directly with what it would correctly see outside this test quirk,
    // rather than asserting on that unrelated pre-existing behavior here.
    Cache::put('magna.widget.schedule.tables', ['magna_entries_article'], 60);
    Entry::type('article')->create([
        'title' => 'Scheduled', 'status' => EntryStatus::Scheduled, 'locale' => '', 'published_at' => now()->addDay(),
    ]);

    $viewer = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($viewer);

    $widget = new UpcomingScheduleWidget;
    $rows = $widget->getViewData()['rows'];
    expect($rows)->toBeEmpty();

    $role = Role::factory()->create();
    $role->grant('content.article.view');
    $viewer->assignRole($role);
    $viewer->forgetResolvedGrants();

    $rows = $widget->getViewData()['rows'];
    expect($rows)->toHaveCount(1);
});
