<?php

declare(strict_types=1);

namespace Magna\Delivery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Magna\Content\Entry;
use Magna\Content\EntryStatus;
use Magna\Content\FieldTypes\RelationField;
use Magna\Content\SchemaRegistry;
use Magna\Delivery\EntryTransformer;
use Magna\Delivery\ETagService;
use Magna\Delivery\PreviewTokenService;
use Magna\Delivery\RelationLoader;
use Magna\Delivery\SurrogateKeyCollector;
use Magna\Media\Media;
use Symfony\Component\HttpFoundation\Response;

final class ContentSingleController extends Controller
{
    public function __construct(
        private readonly SchemaRegistry $schema,
        private readonly EntryTransformer $transformer,
        private readonly RelationLoader $relationLoader,
        private readonly ETagService $etag,
        private readonly PreviewTokenService $previewTokens,
    ) {}

    public function __invoke(Request $request, string $type, string $id): Response
    {
        $contentType = $this->schema->get($type);
        if ($contentType === null) {
            return response()->json(['message' => "Content type '{$type}' not found."], 404);
        }

        $keys = new SurrogateKeyCollector;
        $keys->addType($type);

        $cacheKey = $this->etag->cacheKey($request);
        $matchedEtag = $this->etag->check($request, $cacheKey, $type);
        if ($matchedEtag !== null) {
            return response('', 304)->header('ETag', $matchedEtag);
        }

        $preview = $request->boolean('preview');
        $previewToken = $request->string('preview_token')->value();

        // Build query — allow drafts if a preview token is present
        $query = Entry::type($type);
        if ($preview && $previewToken !== '') {
            $query->whereIn('status', [EntryStatus::Published->value, EntryStatus::Draft->value]);
        } else {
            $query->where('status', EntryStatus::Published->value);
        }

        // Resolve by ULID or slug
        $isUlid = (bool) preg_match('/^[0-9A-Z]{26}$/i', $id);
        $hasSlug = $contentType->getField('slug') !== null;

        if ($isUlid) {
            $query->where('id', strtolower($id));
        } elseif ($hasSlug) {
            $query->where('slug', $id);
        } else {
            return response()->json(['message' => 'Entry not found.'], 404);
        }

        $entry = $query->first();
        if ($entry === null) {
            return response()->json(['message' => 'Entry not found.'], 404);
        }

        // Validate preview token — it is entry-scoped, so we check after finding the entry
        if ($preview && $previewToken !== '') {
            if (! $this->previewTokens->validate($previewToken, $entry->id, $type)) {
                return response()->json(['message' => 'Invalid or expired preview token.'], 403);
            }
        }

        // Parse ?with=
        $withParam = $request->string('with')->value();
        /** @var list<string> $relationHandles */
        $relationHandles = [];
        if ($withParam !== '') {
            foreach (array_map('trim', explode(',', $withParam)) as $handle) {
                $field = $contentType->getField($handle);
                if ($field === null || ! $field->type instanceof RelationField) {
                    return response()->json(['message' => "Unknown relation field: '{$handle}'."], 400);
                }
                $relationHandles[] = $handle;
            }
        }

        // Parse ?fields=
        $fieldsParam = $request->string('fields')->value();
        $fields = $fieldsParam !== '' ? array_map('trim', explode(',', $fieldsParam)) : null;

        // Batch-load media
        $entries = collect([$entry]);
        $mediaIds = $this->transformer->collectMediaIds($entries, $contentType);

        /** @var Collection<array-key, Media> $mediaCache */
        $mediaCache = $mediaIds !== []
            ? Media::whereIn('id', $mediaIds)->get()->keyBy('id')
            : collect();

        $relations = $relationHandles !== []
            ? $this->relationLoader->load($entries, $relationHandles, $type)
            : [];

        $data = $this->transformer->transformOne($entry, $contentType, $fields, $relations, $mediaCache, $keys);

        $body = ['data' => $data];
        $json = json_encode($body);
        if ($json === false) {
            return response()->json(['message' => 'Response serialization failed.'], 500);
        }

        $etagValue = '"'.hash('sha256', $json).'"';
        $this->etag->store($cacheKey, $etagValue, $type);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'ETag' => $etagValue,
            'Cache-Control' => 'public, s-maxage=60, stale-while-revalidate=300',
            'Surrogate-Keys' => $keys->headerValue(),
        ]);
    }
}
