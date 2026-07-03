<?php

declare(strict_types=1);

namespace Magna\Delivery\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Magna\Content\Field;
use Magna\Content\FieldTypes\RelationField;
use Magna\Content\SchemaRegistry;
use Magna\Delivery\CursorPaginator;
use Magna\Delivery\DeliveryQueryBuilder;
use Magna\Delivery\EntryTransformer;
use Magna\Delivery\ETagService;
use Magna\Delivery\Exceptions\DeliveryException;
use Magna\Delivery\RelationLoader;
use Magna\Delivery\SurrogateKeyCollector;
use Magna\Media\Media;
use Symfony\Component\HttpFoundation\Response;

final class ContentListController extends Controller
{
    public function __construct(
        private readonly SchemaRegistry $schema,
        private readonly DeliveryQueryBuilder $queryBuilder,
        private readonly CursorPaginator $paginator,
        private readonly EntryTransformer $transformer,
        private readonly RelationLoader $relationLoader,
        private readonly ETagService $etag,
    ) {}

    public function __invoke(Request $request, string $type): Response
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

        // Parse ?with= relation handles
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

        // Parse ?sort=
        $sortParam = $request->string('sort')->value();
        $sortAsc = ! str_starts_with($sortParam, '-');
        $sortColumn = ltrim($sortParam, '-');

        if ($sortColumn === '') {
            $sortColumn = 'id';
            $sortAsc = false;
        }

        // Validate sort column against schema
        $validSortColumns = array_merge(
            ['id', 'published_at', 'created_at', 'updated_at'],
            array_map(fn (Field $f): string => $f->handle, $contentType->columnFields()),
        );
        if (! in_array($sortColumn, $validSortColumns, true)) {
            return response()->json(['message' => "Invalid sort column: '{$sortColumn}'."], 400);
        }

        // Parse ?per_page= and ?cursor=
        $perPageRaw = $request->input('per_page', 25);
        $perPage = min(max(is_numeric($perPageRaw) ? (int) $perPageRaw : 25, 1), 100);
        $cursor = $request->string('cursor')->value();
        $cursor = $cursor !== '' ? $cursor : null;

        // Build and execute query
        try {
            $query = $this->queryBuilder->build($contentType, $request);
            $paginated = $this->paginator->paginate($query, $perPage, $cursor, $sortColumn, $sortAsc);
        } catch (DeliveryException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }

        $entries = $paginated->entries;

        // Batch-load media (Q: 1 query if any media fields exist)
        $mediaIds = $this->transformer->collectMediaIds($entries, $contentType);

        /** @var Collection<array-key, Media> $mediaCache */
        $mediaCache = $mediaIds !== []
            ? Media::whereIn('id', $mediaIds)->get()->keyBy('id')
            : collect();

        // Batch-load relations (Q: 1 pivot + 1 per distinct relation type)
        $relations = $relationHandles !== []
            ? $this->relationLoader->load($entries, $relationHandles, $type)
            : [];

        $data = $this->transformer->transformMany($entries, $contentType, $fields, $relations, $mediaCache, $keys);

        $body = [
            'data' => $data,
            'meta' => [
                'next_cursor' => $paginated->nextCursor,
                'has_more' => $paginated->hasMore,
                'per_page' => $paginated->perPage,
            ],
            'included' => (object) [],
        ];

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
