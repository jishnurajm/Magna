<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Content\ContentType;
use Magna\Content\Entry;
use Magna\Content\EntryStatus;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function singleDeliveryToken(): string
{
    $user = User::factory()->create();
    $result = $user->createToken('single-delivery', ['delivery'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'delivery'])->save();

    return $result->plainTextToken;
}

function registerSingleArticleType(): void
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
            ['handle' => 'slug', 'type' => 'slug', 'from' => 'title'],
            ['handle' => 'body', 'type' => 'textarea'],
        ],
    ], app(FieldTypeRegistry::class));
    $registry->register($type);
    app(SchemaSyncer::class)->syncAll($registry, allowDestructive: true);
}

// ── Fetch by ULID ─────────────────────────────────────────────────────────────

it('returns a single entry by ULID', function (): void {
    registerSingleArticleType();

    $entry = Entry::type('article')->create([
        'title' => 'Fetchable',
        'slug' => 'fetchable',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = singleDeliveryToken();

    $this->getJson("/api/v1/content/article/{$entry->id}", ['Authorization' => 'Bearer '.$token])
        ->assertStatus(200)
        ->assertJsonPath('data.id', $entry->id)
        ->assertJsonPath('data.type', 'article')
        ->assertJsonPath('data.title', 'Fetchable');
});

// ── Fetch by slug ─────────────────────────────────────────────────────────────

it('returns a single entry by slug', function (): void {
    registerSingleArticleType();

    Entry::type('article')->create([
        'title' => 'Slugged',
        'slug' => 'my-slug',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = singleDeliveryToken();

    $this->getJson('/api/v1/content/article/my-slug', ['Authorization' => 'Bearer '.$token])
        ->assertStatus(200)
        ->assertJsonPath('data.slug', 'my-slug');
});

// ── 404 for draft without preview token ───────────────────────────────────────

it('returns 404 for a draft entry without a preview token', function (): void {
    registerSingleArticleType();

    $draft = Entry::type('article')->create([
        'title' => 'Unpublished',
        'slug' => 'unpublished',
        'status' => EntryStatus::Draft,
        'locale' => '',
    ]);

    $token = singleDeliveryToken();

    $this->getJson("/api/v1/content/article/{$draft->id}", ['Authorization' => 'Bearer '.$token])
        ->assertStatus(404);
});

// ── 404 for non-existent entry ─────────────────────────────────────────────────

it('returns 404 when the entry does not exist', function (): void {
    registerSingleArticleType();
    $token = singleDeliveryToken();

    $this->getJson('/api/v1/content/article/01JZZZZZZZZZZZZZZZZZZZZZZ', ['Authorization' => 'Bearer '.$token])
        ->assertStatus(404);
});

// ── Surrogate-Keys header ─────────────────────────────────────────────────────

it('single response includes type and entry surrogate keys', function (): void {
    registerSingleArticleType();

    $entry = Entry::type('article')->create([
        'title' => 'Keyed',
        'slug' => 'keyed',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = singleDeliveryToken();

    $response = $this->getJson("/api/v1/content/article/{$entry->id}", [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(200);
    $surrogateKeys = $response->headers->get('Surrogate-Keys') ?? '';

    expect($surrogateKeys)->toContain('type:article');
    expect($surrogateKeys)->toContain('entry:'.$entry->id);
});

// ── Field selection ────────────────────────────────────────────────────────────

it('single endpoint respects ?fields= selection', function (): void {
    registerSingleArticleType();

    $entry = Entry::type('article')->create([
        'title' => 'Filtered',
        'slug' => 'filtered',
        'body' => 'Some body text',
        'status' => EntryStatus::Published,
        'locale' => '',
        'published_at' => now(),
    ]);

    $token = singleDeliveryToken();

    $response = $this->getJson("/api/v1/content/article/{$entry->id}?fields=title", [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveKey('title');
    expect($data)->not->toHaveKey('body');
});
