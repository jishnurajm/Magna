<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Magna\Content\ContentType;
use Magna\Content\Entry;
use Magna\Content\EntryStatus;
use Magna\Content\Field;
use Magna\Delivery\Exceptions\DeliveryException;

/**
 * Builds a base Entry query from request parameters.
 *
 * Handles status filtering, and applies the ?filter[field][op]=value parameter
 * with a safe operator allowlist to prevent SQL injection.
 */
final class DeliveryQueryBuilder
{
    /** @var list<string> */
    private const SAFE_OPERATORS = ['eq', 'neq', 'lt', 'lte', 'gt', 'gte', 'like', 'in', 'nin'];

    /** @var array<string, string> */
    private const OP_MAP = [
        'eq' => '=',
        'neq' => '!=',
        'lt' => '<',
        'lte' => '<=',
        'gt' => '>',
        'gte' => '>=',
        'like' => 'LIKE',
    ];

    /**
     * Build a base query for the given content type, pre-filtered by status.
     *
     * @return Builder<Entry>
     *
     * @throws DeliveryException on invalid filter column, operator, or injection attempt
     */
    public function build(
        ContentType $type,
        Request $request,
    ): Builder {
        $query = Entry::type($type->handle);
        $query->where('status', EntryStatus::Published->value);

        $filterRaw = $request->input('filter');
        if (is_array($filterRaw)) {
            $this->applyFilters($query, $filterRaw, $type);
        }

        return $query;
    }

    /**
     * Same as build() but includes draft entries when a valid preview context is
     * signalled by the caller.
     *
     * @return Builder<Entry>
     *
     * @throws DeliveryException
     */
    public function buildWithPreview(ContentType $type, Request $request): Builder
    {
        $query = Entry::type($type->handle);
        $query->whereIn('status', [EntryStatus::Published->value, EntryStatus::Draft->value]);

        $filterRaw = $request->input('filter');
        if (is_array($filterRaw)) {
            $this->applyFilters($query, $filterRaw, $type);
        }

        return $query;
    }

    /**
     * @param  Builder<Entry>  $query
     * @param  array<mixed, mixed>  $filters
     *
     * @throws DeliveryException
     */
    private function applyFilters(Builder $query, array $filters, ContentType $type): void
    {
        $validColumns = $this->validFilterColumns($type);

        foreach ($filters as $column => $conditions) {
            if (! is_string($column)) {
                throw new DeliveryException('Filter column key must be a string.');
            }

            if (! in_array($column, $validColumns, true)) {
                throw new DeliveryException("Unknown or disallowed filter column: '{$column}'.");
            }

            if (! is_array($conditions)) {
                // Simple equality shorthand: ?filter[field]=value
                $query->where($column, '=', $conditions);

                continue;
            }

            foreach ($conditions as $op => $value) {
                if (! is_string($op)) {
                    throw new DeliveryException('Filter operator must be a string.');
                }

                if (! in_array($op, self::SAFE_OPERATORS, true)) {
                    throw new DeliveryException(
                        "Filter operator '{$op}' is not allowed. Permitted: ".implode(', ', self::SAFE_OPERATORS).'.'
                    );
                }

                if ($op === 'in') {
                    $query->whereIn($column, is_array($value) ? $value : [$value]);
                } elseif ($op === 'nin') {
                    $query->whereNotIn($column, is_array($value) ? $value : [$value]);
                } else {
                    // PHPStan proves $op is a key of OP_MAP at this point (in/nin handled above,
                    // and SAFE_OPERATORS ⊇ OP_MAP keys — all remaining ops are in the map).
                    $query->where($column, self::OP_MAP[$op], $value);
                }
            }
        }
    }

    /** @return list<string> */
    private function validFilterColumns(ContentType $type): array
    {
        $base = ['id', 'status', 'locale', 'published_at', 'author_id', 'created_at', 'updated_at'];
        $fieldHandles = array_map(fn (Field $f): string => $f->handle, $type->columnFields());

        return array_merge($base, $fieldHandles);
    }
}
