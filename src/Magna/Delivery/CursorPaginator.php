<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Magna\Content\Entry;
use Magna\Delivery\Exceptions\DeliveryException;

/**
 * ULID-keyset cursor paginator.
 *
 * The cursor token is a URL-safe base64-encoded ULID of the last seen entry.
 * Standard base64 +/ characters are replaced with -_ (no padding) to avoid
 * misinterpretation of + as space in URL query strings.
 * Sort direction determines whether we walk forward (id > cursor) or backward
 * (id < cursor). ULIDs encode time so the default DESC sort yields newest-first.
 */
final class CursorPaginator
{
    /**
     * @param  Builder<Entry>  $query
     *
     * @throws DeliveryException if the cursor is malformed
     */
    public function paginate(
        Builder $query,
        int $perPage,
        ?string $encodedCursor,
        string $sortColumn = 'id',
        bool $sortAsc = false,
    ): PaginatedResult {
        $direction = $sortAsc ? 'asc' : 'desc';

        if ($encodedCursor !== null) {
            // Restore standard base64 from the URL-safe variant before decoding.
            $decoded = base64_decode(strtr($encodedCursor, '-_', '+/'), strict: true);
            if ($decoded === false || ! preg_match('/^[0-9A-Z]{26}$/i', $decoded)) {
                throw new DeliveryException('Invalid or malformed cursor.');
            }
            $cursorId = strtolower($decoded);

            $cursorOp = $sortAsc ? '>' : '<';
            $query->where('id', $cursorOp, $cursorId);
        }

        if ($sortColumn !== 'id') {
            $query->orderBy($sortColumn, $direction)->orderBy('id', $direction);
        } else {
            $query->orderBy('id', $direction);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Entry> $rows */
        $rows = $query->limit($perPage + 1)->get();

        $hasMore = $rows->count() > $perPage;

        /** @var Collection<int, Entry> $entries */
        $entries = ($hasMore ? $rows->slice(0, $perPage) : $rows)->values();

        $nextCursor = null;
        if ($hasMore) {
            $last = $entries->last();
            if ($last instanceof Entry) {
                // URL-safe base64: replace +/ with -_ and strip = padding.
                $nextCursor = rtrim(strtr(base64_encode($last->id), '+/', '-_'), '=');
            }
        }

        return new PaginatedResult(
            entries: $entries,
            nextCursor: $nextCursor,
            hasMore: $hasMore,
            perPage: $perPage,
        );
    }
}
