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
use Magna\Delivery\ETagService;
use Magna\Media\Media;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function etagToken(): string
{
    $user = User::factory()->create();
    $result = $user->createToken('etag-test', ['delivery'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'delivery'])->save();

    return $result->plainTextToken;
}

function setupEtagType(): void
{
    /** @var SchemaRegistry $registry */
    $registry = app(SchemaRegistry::class);
    $type = ContentType::fromArray([
        'handle' => 'article',
        'displayName' => 'Article',
        'localizable' => false,
        'draftable' => true,
        'fields' => [
            ['handle' => 'title', 'type' => 'text', 'required' => true],
            ['handle' => 'slug', 'type' => 'slug'],
        ],
    ], app(FieldTypeRegistry::class));
    $registry->register($type);
    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);
}

// ── ETag on first response ────────────────────────────────────────────────────

it('first response includes a quoted ETag header', function (): void {
    setupEtagType();

    Entry::type('article')->create([
        'title' => 'ETag Entry',
        'slug' => 'etag-entry',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = etagToken();

    $response = $this->getJson('/api/v1/content/article', ['Authorization' => 'Bearer '.$token]);

    $response->assertStatus(200);
    $etag = $response->headers->get('ETag');
    expect($etag)->not->toBeNull();
    expect($etag)->toStartWith('"')->toEndWith('"');
});

// ── 304 with zero content queries ─────────────────────────────────────────────

it('conditional request returns 304 with zero content queries when ETag matches', function (): void {
    setupEtagType();

    Entry::type('article')->create([
        'title' => '304 Test',
        'slug' => '304-test',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = etagToken();

    // First request — populate ETag cache
    $first = $this->getJson('/api/v1/content/article', ['Authorization' => 'Bearer '.$token]);
    $first->assertStatus(200);
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();

    // Second request with If-None-Match
    DB::enableQueryLog();

    $second = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'If-None-Match' => (string) $etag,
    ])->getJson('/api/v1/content/article');

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $second->assertStatus(304);

    // The 304 path must not run any content queries (only the token lookup)
    $contentQueries = array_filter(
        $queries,
        fn (array $q): bool => str_contains($q['query'], 'magna_entries_')
    );
    expect(count($contentQueries))->toBe(0);
});

// ── 200 when ETag does NOT match ──────────────────────────────────────────────

it('returns 200 when If-None-Match does not match the stored ETag', function (): void {
    setupEtagType();

    Entry::type('article')->create([
        'title' => 'Fresh',
        'slug' => 'fresh',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = etagToken();

    // Seed the cache with a first request
    $this->getJson('/api/v1/content/article', ['Authorization' => 'Bearer '.$token])->assertStatus(200);

    // Stale ETag
    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'If-None-Match' => '"stale-hash-that-does-not-match"',
    ])->getJson('/api/v1/content/article')->assertStatus(200);
});

// ── ETagService::invalidateType ───────────────────────────────────────────────

it('invalidateType flushes the ETag cache so the next request returns 200', function (): void {
    setupEtagType();

    Entry::type('article')->create([
        'title' => 'Invalidation Test',
        'slug' => 'invalidation',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = etagToken();

    $first = $this->getJson('/api/v1/content/article', ['Authorization' => 'Bearer '.$token]);
    $first->assertStatus(200);
    $etag = $first->headers->get('ETag');

    // Simulate a publish event invalidating the cache
    app(ETagService::class)->invalidateType('article');

    // Now the same ETag should not match anymore
    $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
        'If-None-Match' => (string) $etag,
    ])->getJson('/api/v1/content/article')->assertStatus(200);
});

// ── Cache-Control header ──────────────────────────────────────────────────────

it('response includes Cache-Control with stale-while-revalidate', function (): void {
    setupEtagType();
    $token = etagToken();

    $response = $this->getJson('/api/v1/content/article', ['Authorization' => 'Bearer '.$token]);
    $response->assertStatus(200);

    $cc = $response->headers->get('Cache-Control') ?? '';
    expect($cc)->toContain('stale-while-revalidate');
});

// ── Surrogate-Keys on single endpoint include media keys ──────────────────────

it('surrogate keys for a single entry include media ulid when a media field is present', function (): void {
    /** @var SchemaRegistry $registry */
    $registry = app(SchemaRegistry::class);

    $type = ContentType::fromArray([
        'handle' => 'post',
        'displayName' => 'Post',
        'localizable' => false,
        'draftable' => true,
        'fields' => [
            ['handle' => 'title', 'type' => 'text', 'required' => true],
            ['handle' => 'hero', 'type' => 'media'],
        ],
    ], app(FieldTypeRegistry::class));
    $registry->register($type);
    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);

    // Create a media record so the resolver can find it
    $media = Media::factory()->create();

    $entry = Entry::type('post')->create([
        'title' => 'With Media',
        'hero' => $media->id,
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = etagToken();

    $response = $this->getJson("/api/v1/content/post/{$entry->id}", [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(200);
    $surrogateKeys = $response->headers->get('Surrogate-Keys') ?? '';
    expect($surrogateKeys)->toContain('media:'.$media->id);
});
