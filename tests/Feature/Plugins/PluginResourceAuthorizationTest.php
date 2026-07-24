<?php

declare(strict_types=1);

use Magna\Auth\Role;
use Magna\Docs\Filament\Resources\DocCollectionResource;
use Magna\Docs\Filament\Resources\DocPageResource;
use Magna\Docs\Models\DocCollection;
use Magna\Docs\Models\DocPage;
use Magna\Testing\PluginTestCase;
use Magna\Users\User;
use MagnaMarketplace\Filament\Resources\MarketplacePluginResource;
use MagnaMarketplace\Filament\Resources\MarketplaceReportResource;
use MagnaMarketplace\Models\MarketplacePlugin;
use MagnaMarketplace\Models\MarketplaceReport;

uses(PluginTestCase::class);

// Stage 10 (S1-01 residual): DocCollectionResource, DocPageResource,
// MarketplacePluginResource, and MarketplaceReportResource previously had
// zero authorization — any authenticated panel user could manage docs
// content, or approve/reject/dismiss marketplace submissions and abuse
// reports, entirely defeating the review-gate trust boundary Stage 6 relies
// on. These pin the fix.

it('denies docs resources to a user without docs.pages permissions', function (): void {
    $this->enablePlugin('magna/docs');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    $collection = DocCollection::create(['title' => 'Guides', 'slug' => 'guides', 'order' => 0]);
    $page = DocPage::create(['title' => 'Intro', 'slug' => 'intro', 'content' => 'x', 'status' => 'published', 'order' => 0]);

    expect(DocCollectionResource::canViewAny())->toBeFalse()
        ->and(DocCollectionResource::canEdit($collection))->toBeFalse()
        ->and(DocPageResource::canViewAny())->toBeFalse()
        ->and(DocPageResource::canEdit($page))->toBeFalse();
});

it('grants docs resources to a user with docs.pages.manage', function (): void {
    $this->enablePlugin('magna/docs');

    $role = Role::factory()->create();
    $role->grant('docs.pages.view', 'docs.pages.manage');
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $collection = DocCollection::create(['title' => 'Guides', 'slug' => 'guides2', 'order' => 0]);

    expect(DocCollectionResource::canViewAny())->toBeTrue()
        ->and(DocCollectionResource::canEdit($collection))->toBeTrue();
});

it('denies marketplace review resources to a user without marketplace.plugins.review', function (): void {
    $this->enablePlugin('magna-cms/marketplace');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    $plugin = MarketplacePlugin::create([
        'package' => 'acme/thing', 'name' => 'Thing', 'short_description' => 'x', 'author' => 'Acme', 'status' => 'submitted',
    ]);
    $report = MarketplaceReport::create([
        'marketplace_plugin_id' => $plugin->id, 'site' => 'site-a', 'reason' => 'spam', 'status' => 'open',
    ]);

    expect(MarketplacePluginResource::canViewAny())->toBeFalse()
        ->and(MarketplacePluginResource::canEdit($plugin))->toBeFalse()
        ->and(MarketplaceReportResource::canViewAny())->toBeFalse()
        ->and(MarketplaceReportResource::canEdit($report))->toBeFalse();
});

it('grants marketplace review resources to a user with marketplace.plugins.review', function (): void {
    $this->enablePlugin('magna-cms/marketplace');

    $role = Role::factory()->create();
    $role->grant('marketplace.plugins.review');
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $plugin = MarketplacePlugin::create([
        'package' => 'acme/thing2', 'name' => 'Thing2', 'short_description' => 'x', 'author' => 'Acme', 'status' => 'submitted',
    ]);

    expect(MarketplacePluginResource::canViewAny())->toBeTrue()
        ->and(MarketplacePluginResource::canEdit($plugin))->toBeTrue();
});
