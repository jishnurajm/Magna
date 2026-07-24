<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Support\Collection;
use Magna\Content\Entry;

/**
 * Value object returned by CursorPaginator.
 *
 * @phpstan-type EntryCollection Collection<int, Entry>
 */
final class PaginatedResult
{
    /**
     * @param  Collection<int, Entry>  $entries
     */
    public function __construct(
        public readonly Collection $entries,
        public readonly ?string $nextCursor,
        public readonly bool $hasMore,
        public readonly int $perPage,
    ) {}
}
