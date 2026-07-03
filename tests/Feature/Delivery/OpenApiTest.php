<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Content\ContentType;
use Magna\Content\FieldTypeRegistry;
use Magna\Content\SchemaRegistry;
use Magna\Content\SchemaSyncer;
use Magna\Delivery\OpenApiGenerator;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function openApiManagementToken(): string
{
    $user = User::factory()->create();
    $result = $user->createToken('openapi-mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

function registerOpenApiType(): void
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

// ── GET /api/v1/openapi.json ──────────────────────────────────────────────────

it('openapi endpoint requires a management token', function (): void {
    $user = User::factory()->create();
    $result = $user->createToken('delivery-only', ['delivery'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'delivery'])->save();
    $deliveryToken = $result->plainTextToken;

    $this->getJson('/api/v1/openapi.json', ['Authorization' => 'Bearer '.$deliveryToken])
        ->assertStatus(403);
});

it('openapi endpoint returns a valid 3.1 spec', function (): void {
    registerOpenApiType();
    $token = openApiManagementToken();

    $response = $this->getJson('/api/v1/openapi.json', ['Authorization' => 'Bearer '.$token]);

    $response->assertStatus(200)
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonStructure(['info', 'components', 'paths']);
});

it('openapi spec includes paths for all registered content types', function (): void {
    registerOpenApiType();
    $token = openApiManagementToken();

    $response = $this->getJson('/api/v1/openapi.json', ['Authorization' => 'Bearer '.$token]);

    $response->assertStatus(200);
    $paths = $response->json('paths');
    expect($paths)->toHaveKey('/api/v1/content/article');
    expect($paths)->toHaveKey('/api/v1/content/article/{id}');
});

it('openapi spec includes schema components for registered types', function (): void {
    registerOpenApiType();
    $token = openApiManagementToken();

    $response = $this->getJson('/api/v1/openapi.json', ['Authorization' => 'Bearer '.$token]);
    $response->assertStatus(200);

    $schemas = $response->json('components.schemas');
    expect($schemas)->toHaveKey('article');
    expect($schemas)->toHaveKey('articleList');
    expect($schemas)->toHaveKey('PaginationMeta');
});

// ── OpenApiGenerator unit ─────────────────────────────────────────────────────

it('OpenApiGenerator includes field schemas from content type definition', function (): void {
    /** @var SchemaRegistry $registry */
    $registry = app(SchemaRegistry::class);
    $type = ContentType::fromArray([
        'handle' => 'post',
        'displayName' => 'Post',
        'localizable' => false,
        'draftable' => true,
        'fields' => [
            ['handle' => 'title', 'type' => 'text', 'required' => true],
            ['handle' => 'published', 'type' => 'boolean'],
        ],
    ], app(FieldTypeRegistry::class));
    $registry->register($type);

    $spec = app(OpenApiGenerator::class)->generate();

    $postSchema = $spec['components']['schemas']['post'] ?? null;
    expect($postSchema)->not->toBeNull();
    expect($postSchema['properties'])->toHaveKey('title');
    expect($postSchema['properties'])->toHaveKey('published');
    expect($postSchema['properties']['title']['type'])->toBe('string');
    expect($postSchema['properties']['published']['type'])->toBe('boolean');
});
