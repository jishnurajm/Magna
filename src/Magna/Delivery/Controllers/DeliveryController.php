<?php

declare(strict_types=1);

namespace Magna\Delivery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Magna\Content\SchemaRegistry;
use Magna\Delivery\ETagService;
use Magna\Delivery\SurrogateKeyCollector;
use Magna\Settings\ApiSettings;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared behavior for the public Delivery API's __invoke() controllers
 * (ContentListController, ContentSingleController): the API-enabled/resolve
 * content-type/304-ETag preamble every one of them starts with, and the
 * cached-body response shape every one of them ends with.
 */
abstract class DeliveryController extends Controller
{
    /**
     * Runs the shared preamble: API-enabled check, content-type resolution,
     * surrogate-key collector setup, and a 304 short-circuit against the
     * request's ETag. Returns a Response to return immediately on
     * failure/304, or a DeliveryRequestContext to continue with on success.
     */
    protected function beginRequest(
        Request $request,
        string $type,
        SchemaRegistry $schema,
        ETagService $etag,
    ): Response|DeliveryRequestContext {
        if (! ApiSettings::get()->api_enabled) {
            return response()->json(['message' => 'The API is currently disabled.'], 503);
        }

        $contentType = $schema->get($type);
        if ($contentType === null) {
            return response()->json(['message' => "Content type '{$type}' not found."], 404);
        }

        $keys = new SurrogateKeyCollector;
        $keys->addType($type);

        $cacheKey = $etag->cacheKey($request);
        $matchedEtag = $etag->check($request, $cacheKey, $type);
        if ($matchedEtag !== null) {
            return response('', 304)->header('ETag', $matchedEtag);
        }

        return new DeliveryRequestContext($contentType, $keys, $cacheKey);
    }

    /** The final JSON response for a fresh (non-cached) body. */
    protected function deliveryResponse(string $json, string $etagValue, SurrogateKeyCollector $keys, string $cacheStatus): Response
    {
        return response($json, 200, [
            'Content-Type' => 'application/json',
            'ETag' => $etagValue,
            'Cache-Control' => 'public, s-maxage=60, stale-while-revalidate=300',
            'Surrogate-Key' => $keys->headerValue(),
            'X-Cache' => $cacheStatus,
        ]);
    }

    protected function cachedResponse(string $body, SurrogateKeyCollector $keys): Response
    {
        return $this->deliveryResponse($body, '"'.hash('sha256', $body).'"', $keys, 'HIT');
    }
}
