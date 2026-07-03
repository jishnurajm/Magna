<?php

declare(strict_types=1);

namespace Magna\Delivery\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Magna\Content\Entry;
use Magna\Content\SchemaRegistry;
use Magna\Delivery\PreviewTokenService;

final class PreviewTokenController extends Controller
{
    public function __construct(
        private readonly SchemaRegistry $schema,
        private readonly PreviewTokenService $previewTokens,
    ) {}

    public function __invoke(Request $request, string $type, string $id): JsonResponse
    {
        $contentType = $this->schema->get($type);
        if ($contentType === null) {
            return response()->json(['message' => "Content type '{$type}' not found."], 404);
        }

        $entry = Entry::type($type)->where('id', $id)->first();
        if ($entry === null) {
            return response()->json(['message' => 'Entry not found.'], 404);
        }

        $ttlRaw = $request->input('ttl_seconds', 3600);
        $ttl = max(1, is_numeric($ttlRaw) ? (int) $ttlRaw : 3600);
        $token = $this->previewTokens->mint($entry->id, $type, $ttl);

        return response()->json([
            'token' => $token,
            'entry_id' => $entry->id,
            'entry_type' => $type,
            'expires_at' => now()->addSeconds($ttl)->toIso8601String(),
        ]);
    }
}
