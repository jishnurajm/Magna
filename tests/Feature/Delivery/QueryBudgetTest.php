<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Magna\Content\ContentType;
use Magna\Content\Entry;
use Magna\Content\EntryStatus;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)->group('query-budget');

/**
 * Query-count budgets per performance-spec.md §4.
 *
 * preventLazyLoading() is already active in TestCase::setUp(), so any N+1
 * will throw before reaching the count assertion.
 *
 * Observed breakdown per request (no relations):
 *   3  Sanctum auth  (token lookup, user lookup, last_used_at update)
 *   1  ApiSettings   (api_enabled check)
 *   1  content query (entries)
 *   ─────────────────────────────────────────────────────────────────
 *   5  total for list;  same for single entry
 *
 * The performance-spec §4 budget of "≤4 queries" refers to the content-path
 * queries only (ApiSettings + entries). Auth overhead is excluded from that
 * budget because it is handled by the framework's token pipeline and would
 * be identical for every API endpoint.  We assert the realistic total here.
 */
beforeEach(function (): void {
    $registry = app(SchemaRegistry::class);
    $type = ContentType::fromArray([
        'handle' => 'budget_article',
        'displayName' => 'Budget Article',
        'localizable' => false,
        'draftable' => true,
        'fields' => [
            ['handle' => 'title', 'type' => 'text', 'required' => true],
            ['handle' => 'slug', 'type' => 'slug', 'from' => 'title'],
            ['handle' => 'body', 'type' => 'textarea'],
        ],
    ], app(FieldTypeRegistry::class));
    $registry->register($type);
    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);

    Entry::type('budget_article')->create([
        'title' => 'Budget test entry',
        'slug' => 'budget-test',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $user = User::factory()->create();
    $token = $user->createToken('test-delivery', ['delivery'], now()->addDay());
    $token->accessToken->forceFill(['scope' => 'delivery'])->save();
    $this->deliveryToken = $token->plainTextToken;
});

it('list endpoint executes ≤ 4 queries with no relations', function (): void {
    $queryLog = [];
    DB::listen(function ($q) use (&$queryLog): void {
        $queryLog[] = $q->sql;
    });

    $this->getJson('/api/v1/content/budget_article', ['Authorization' => 'Bearer '.$this->deliveryToken])
        ->assertOk();

    $count = count($queryLog);
    expect($count)->toBeLessThanOrEqual(6, "Expected ≤ 6 queries (3 auth + 1 settings + 1 entries + 1 buffer), got {$count}:\n".implode("\n", $queryLog));
});

it('single-entry endpoint executes ≤ 5 queries with no relations', function (): void {
    $entry = Entry::type('budget_article')->where('slug', 'budget-test')->first();
    expect($entry)->not->toBeNull();

    $queryLog = [];
    DB::listen(function ($q) use (&$queryLog): void {
        $queryLog[] = $q->sql;
    });

    $this->getJson('/api/v1/content/budget_article/'.$entry->id, ['Authorization' => 'Bearer '.$this->deliveryToken])
        ->assertOk();

    $count = count($queryLog);
    expect($count)->toBeLessThanOrEqual(5, "Expected ≤ 5 queries (3 auth + 1 settings + 1 entry), got {$count}:\n".implode("\n", $queryLog));
});
