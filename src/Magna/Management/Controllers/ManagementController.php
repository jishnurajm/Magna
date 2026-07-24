<?php

declare(strict_types=1);

namespace Magna\Management\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Base class for management API controllers.
 * Provides shared helpers used across all resource controllers.
 */
abstract class ManagementController extends Controller
{
    /** Return the authenticated actor's ULID as a string, or null if unavailable. */
    protected function actorId(): ?string
    {
        $id = auth()->id();
        if (is_string($id)) {
            return $id;
        }
        if (is_int($id)) {
            return (string) $id;
        }

        return null;
    }

    /**
     * Resolve a single record by (case-insensitive) id from an already-scoped
     * query, or a ready-to-return 404 JsonResponse if it doesn't exist.
     *
     * Centralizes the `find(strtolower($id))` + `{$label} not found.` pattern
     * that used to be duplicated across every Management controller.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return TModel|JsonResponse
     */
    protected function findOrNotFound(Builder $query, string $id, string $label): Model|JsonResponse
    {
        $record = (clone $query)
            ->where($query->getModel()->getKeyName(), strtolower($id))
            ->first();

        return $record ?? response()->json(['message' => "{$label} not found."], 404);
    }
}
