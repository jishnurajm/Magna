<?php

declare(strict_types=1);

namespace Magna\Management\Controllers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Magna\Webhooks\Support\NotPrivateUrlRule;
use Magna\Webhooks\WebhookSubscription;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends ManagementController
{
    /** All event keys the system can emit (core + any plugin-registered). */
    public const CORE_EVENTS = [
        'entry.created',
        'entry.published',
        'entry.updated',
        'entry.unpublished',
        'entry.deleted',
        'media.created',
        'media.deleted',
    ];

    public function index(): JsonResponse
    {
        Gate::authorize('webhooks.manage');

        /** @var Collection<int, WebhookSubscription> $subs */
        $subs = WebhookSubscription::query()->orderByDesc('created_at')->get();

        return response()->json([
            'data' => $subs->map(fn (WebhookSubscription $s): array => $this->subToArray($s))->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('webhooks.manage');

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048', new NotPrivateUrlRule],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $sub = WebhookSubscription::create([
            'url' => $validated['url'],
            'secret' => Str::random(32),
            'events' => $validated['events'],
            'description' => $validated['description'] ?? null,
            'active' => true,
        ]);

        return response()->json(['data' => $this->subToArray($sub)], 201);
    }

    public function show(string $webhook): JsonResponse
    {
        Gate::authorize('webhooks.manage');

        $sub = $this->findOrNotFound(WebhookSubscription::query(), $webhook, 'Webhook');
        if ($sub instanceof JsonResponse) {
            return $sub;
        }

        return response()->json(['data' => $this->subToArray($sub)]);
    }

    public function update(Request $request, string $webhook): JsonResponse
    {
        Gate::authorize('webhooks.manage');

        $sub = $this->findOrNotFound(WebhookSubscription::query(), $webhook, 'Webhook');
        if ($sub instanceof JsonResponse) {
            return $sub;
        }

        $validated = $request->validate([
            'url' => ['sometimes', 'url', 'max:2048', new NotPrivateUrlRule],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $sub->fill($validated);
        $sub->save();

        return response()->json(['data' => $this->subToArray($sub)]);
    }

    public function destroy(string $webhook): Response
    {
        Gate::authorize('webhooks.manage');

        $sub = $this->findOrNotFound(WebhookSubscription::query(), $webhook, 'Webhook');
        if ($sub instanceof JsonResponse) {
            return $sub;
        }

        $sub->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function subToArray(WebhookSubscription $sub): array
    {
        return [
            'id' => $sub->id,
            'url' => $sub->url,
            'secret' => $sub->secret,
            'events' => $sub->events,
            'active' => $sub->active,
            'description' => $sub->description,
            'created_at' => $sub->created_at->toIso8601String(),
            'updated_at' => $sub->updated_at->toIso8601String(),
        ];
    }
}
