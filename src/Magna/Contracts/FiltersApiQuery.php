<?php

declare(strict_types=1);

namespace Magna\Contracts;

/**
 * Plugin contract: scopes or extends delivery API queries for a content type.
 * Semver-guaranteed from core 1.0. Wired to the delivery API in Stage 8.
 *
 * @todo Stage 8 — replace mixed $query with the typed Eloquent Builder once the
 *       Content Engine exists.
 */
interface FiltersApiQuery
{
    /**
     * Receive an Eloquent query builder and return it (possibly modified).
     * Return the query unchanged to be a no-op.
     */
    public function filterApiQuery(string $contentType, mixed $query): mixed;
}
