<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Magna\Auth\Role;
use Magna\Users\User;
use Magna\Webhooks\Jobs\DispatchWebhookJob;
use Magna\Webhooks\WebhookDelivery;
use Magna\Webhooks\WebhookSubscription;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function webhookAdminToken(): string
{
    $role = Role::factory()->create();
    $role->grant('webhooks.manage');

    $user = User::factory()->create();
    $user->assignRole($role);

    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

function noPermToken(): string
{
    $user = User::factory()->create();
    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

// ── CRUD ──────────────────────────────────────────────────────────────────────

it('creates a webhook subscription and generates a secret', function (): void {
    $token = webhookAdminToken();

    $response = $this->withToken($token)
        ->postJson('/api/v1/manage/webhooks', [
            'url' => 'https://example.com/hook',
            'events' => ['entry.published'],
            'description' => 'My hook',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'url', 'secret', 'events', 'active', 'description']]);

    expect($response->json('data.secret'))->not->toBeEmpty()
        ->and($response->json('data.url'))->toBe('https://example.com/hook')
        ->and($response->json('data.events'))->toContain('entry.published');
});

it('lists webhook subscriptions', function (): void {
    $token = webhookAdminToken();

    WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret123',
        'events' => ['entry.published'],
        'active' => true,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/manage/webhooks')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('shows a single webhook', function (): void {
    $token = webhookAdminToken();

    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret123',
        'events' => ['entry.created'],
        'active' => true,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/manage/webhooks/'.$sub->id)
        ->assertOk()
        ->assertJsonPath('data.id', $sub->id);
});

it('updates a webhook subscription', function (): void {
    $token = webhookAdminToken();

    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret123',
        'events' => ['entry.created'],
        'active' => true,
    ]);

    $this->withToken($token)
        ->putJson('/api/v1/manage/webhooks/'.$sub->id, ['active' => false])
        ->assertOk()
        ->assertJsonPath('data.active', false);
});

it('deletes a webhook subscription', function (): void {
    $token = webhookAdminToken();

    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret123',
        'events' => ['entry.published'],
        'active' => true,
    ]);

    $this->withToken($token)
        ->deleteJson('/api/v1/manage/webhooks/'.$sub->id)
        ->assertNoContent();

    expect(WebhookSubscription::find($sub->id))->toBeNull();
});

it('returns 403 when user lacks webhooks.manage permission', function (): void {
    $token = noPermToken();

    $this->withToken($token)
        ->getJson('/api/v1/manage/webhooks')
        ->assertForbidden();
});

// ── Signature verification ────────────────────────────────────────────────────

it('dispatches a job with a verifiable HMAC-SHA256 signature', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $secret = 'test-secret-abc123';
    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => $secret,
        'events' => ['entry.published'],
        'active' => true,
    ]);

    $delivery = WebhookDelivery::create([
        'subscription_id' => $sub->id,
        'event' => 'entry.published',
        'payload' => ['event' => 'entry.published', 'entry_id' => 'abc'],
        'status' => 'pending',
        'attempts' => 0,
    ]);

    $job = new DispatchWebhookJob($delivery->id);
    app()->call([$job, 'handle']);

    $delivery->refresh();
    expect($delivery->status)->toBe('delivered');

    // Verify the sent request carries a signature over timestamp.body (not body alone).
    // The timestamp binding prevents replay attacks — consumers can enforce a time window.
    Http::assertSent(function (Request $request) use ($secret): bool {
        $body = (string) $request->body();
        $tsHeader = $request->header('X-Magna-Timestamp');
        $timestamp = is_array($tsHeader) ? ($tsHeader[0] ?? '') : (string) $tsHeader;
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return $request->hasHeader('X-Magna-Signature-256', $expected)
            && $request->hasHeader('X-Magna-Timestamp');
    });
});

// ── Retry and dead-letter ─────────────────────────────────────────────────────

it('marks a delivery as dead after failed() is called', function (): void {
    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret',
        'events' => ['entry.published'],
        'active' => true,
    ]);

    $delivery = WebhookDelivery::create([
        'subscription_id' => $sub->id,
        'event' => 'entry.published',
        'payload' => ['event' => 'entry.published'],
        'status' => 'pending',
        'attempts' => 0,
    ]);

    $job = new DispatchWebhookJob($delivery->id);
    $job->failed(new RuntimeException('Permanent failure'));

    $delivery->refresh();
    expect($delivery->status)->toBe('dead');
});

it('retries a dead delivery via API and resets status to pending', function (): void {
    Queue::fake();

    $token = webhookAdminToken();

    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret',
        'events' => ['entry.published'],
        'active' => true,
    ]);

    $delivery = WebhookDelivery::create([
        'subscription_id' => $sub->id,
        'event' => 'entry.published',
        'payload' => ['event' => 'entry.published'],
        'status' => 'dead',
        'attempts' => 6,
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/manage/webhooks/'.$sub->id.'/deliveries/'.$delivery->id.'/retry')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    $delivery->refresh();
    expect($delivery->status)->toBe('pending')
        ->and($delivery->attempts)->toBe(0);

    Queue::assertPushed(DispatchWebhookJob::class);
});

it('returns 422 when retrying an already delivered delivery', function (): void {
    $token = webhookAdminToken();

    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret',
        'events' => ['entry.published'],
        'active' => true,
    ]);

    $delivery = WebhookDelivery::create([
        'subscription_id' => $sub->id,
        'event' => 'entry.published',
        'payload' => ['event' => 'entry.published'],
        'status' => 'delivered',
        'attempts' => 1,
    ]);

    $this->withToken($token)
        ->postJson('/api/v1/manage/webhooks/'.$sub->id.'/deliveries/'.$delivery->id.'/retry')
        ->assertStatus(422);
});

it('lists deliveries for a webhook', function (): void {
    $token = webhookAdminToken();

    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret',
        'events' => ['entry.published'],
        'active' => true,
    ]);

    WebhookDelivery::create([
        'subscription_id' => $sub->id,
        'event' => 'entry.published',
        'payload' => ['event' => 'entry.published'],
        'status' => 'delivered',
        'attempts' => 1,
    ]);

    $response = $this->withToken($token)
        ->getJson('/api/v1/manage/webhooks/'.$sub->id.'/deliveries')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('meta.total'))->toBe(1);
});

// Stage 13 (S5-02): webhook_deliveries had no pruning mechanism at all —
// grew unbounded (one row per active subscription on every content/media
// mutation).
it('magna:webhooks:prune-deliveries deletes only entries older than the retention window', function (): void {
    $sub = WebhookSubscription::create([
        'url' => 'https://example.com/hook',
        'secret' => 'secret',
        'events' => ['entry.published'],
        'active' => true,
    ]);

    $old = WebhookDelivery::create([
        'subscription_id' => $sub->id,
        'event' => 'entry.published',
        'payload' => [],
        'status' => 'delivered',
        'attempts' => 1,
    ]);
    $old->forceFill(['created_at' => now()->subDays(100)])->save();

    $recent = WebhookDelivery::create([
        'subscription_id' => $sub->id,
        'event' => 'entry.published',
        'payload' => [],
        'status' => 'delivered',
        'attempts' => 1,
    ]);

    Artisan::call('magna:webhooks:prune-deliveries', ['--days' => '90']);

    expect(WebhookDelivery::find($old->id))->toBeNull()
        ->and(WebhookDelivery::find($recent->id))->not->toBeNull();
});
