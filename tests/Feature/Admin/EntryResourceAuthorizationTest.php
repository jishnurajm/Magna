<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Admin\Resources\EntryResource;
use Magna\Auth\Role;
use Magna\Content\ContentType;
use Magna\Content\EntryManager;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// S1-01 regression: EntryResource previously had zero authorization — any
// authenticated panel user could view/create/edit/delete every entry. These
// tests pin the content.{type}.{action} gates now wired into the resource.

function entryAuthzRegisterType(string $handle): void
{
    $registry = app(SchemaRegistry::class);
    $type = ContentType::fromArray([
        'handle' => $handle,
        'displayName' => 'Entry Authz Test',
        'localizable' => false,
        'draftable' => true,
        'fields' => [['handle' => 'title', 'type' => 'text']],
    ], app(FieldTypeRegistry::class));
    $registry->register($type);
    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);
}

function entryAuthzWithType(string $handle): void
{
    request()->query->set('type', $handle);
}

it('denies view and create with zero content permissions', function (): void {
    entryAuthzRegisterType('authz_article_none');
    entryAuthzWithType('authz_article_none');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    expect(EntryResource::canViewAny())->toBeFalse()
        ->and(EntryResource::canCreate())->toBeFalse();
});

it('denies view/create/edit when no type is selected in the request', function (): void {
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    request()->query->remove('type');

    expect(EntryResource::canViewAny())->toBeFalse()
        ->and(EntryResource::canCreate())->toBeFalse();
});

it('grants view-only access with content.{type}.view, nothing else', function (): void {
    entryAuthzRegisterType('authz_article_view');
    entryAuthzWithType('authz_article_view');

    $role = Role::factory()->create();
    $role->grant('content.authz_article_view.view');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $entry = app(EntryManager::class)->create('authz_article_view', ['title' => 'x'], $user->id);

    expect(EntryResource::canViewAny())->toBeTrue()
        ->and(EntryResource::canCreate())->toBeFalse()
        ->and(EntryResource::canEdit($entry))->toBeFalse()
        ->and(EntryResource::canDelete($entry))->toBeFalse();
});

it('grants edit access for content.{type}.update scoped to that type only', function (): void {
    entryAuthzRegisterType('authz_article_mine');
    entryAuthzRegisterType('authz_article_other');

    $role = Role::factory()->create();
    $role->grant('content.authz_article_mine.view', 'content.authz_article_mine.update');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $ownEntry = app(EntryManager::class)->create('authz_article_mine', ['title' => 'x'], $user->id);
    $otherEntry = app(EntryManager::class)->create('authz_article_other', ['title' => 'y'], $user->id);

    expect(EntryResource::canEdit($ownEntry))->toBeTrue()
        ->and(EntryResource::canEdit($otherEntry))->toBeFalse();
});

it('grants delete access only with content.{type}.delete', function (): void {
    entryAuthzRegisterType('authz_article_delete');

    $role = Role::factory()->create();
    $role->grant('content.authz_article_delete.view', 'content.authz_article_delete.delete');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $entry = app(EntryManager::class)->create('authz_article_delete', ['title' => 'x'], $user->id);

    expect(EntryResource::canDelete($entry))->toBeTrue();
});

it('super admin has full EntryResource access without explicit content grants', function (): void {
    entryAuthzRegisterType('authz_article_super');
    entryAuthzWithType('authz_article_super');

    $role = Role::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $entry = app(EntryManager::class)->create('authz_article_super', ['title' => 'x'], $user->id);

    expect(EntryResource::canViewAny())->toBeTrue()
        ->and(EntryResource::canCreate())->toBeTrue()
        ->and(EntryResource::canEdit($entry))->toBeTrue()
        ->and(EntryResource::canDelete($entry))->toBeTrue();
});
