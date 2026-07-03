<?php

declare(strict_types=1);

namespace Magna\Delivery;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Magna\Content\Entry;
use Magna\Content\EntryStatus;

/**
 * Batch-loads relation entries for a set of source entries.
 *
 * Uses the magna_relations pivot in a single query to avoid N+1 issues,
 * then fetches the target entries grouped by type (one query per distinct
 * relation target type).
 */
final class RelationLoader
{
    /**
     * @param  Collection<int, Entry>  $entries  Source entries to load relations for
     * @param  list<string>  $fieldHandles  Relation field handles to populate
     * @param  string  $typeHandle  Content type handle of the source entries
     * @return array<string, array<string, array<int, Entry>>> [field => [from_id => [Entry, ...]]]
     */
    public function load(Collection $entries, array $fieldHandles, string $typeHandle): array
    {
        if ($entries->isEmpty() || $fieldHandles === []) {
            return [];
        }

        /** @var list<string> $entryIds */
        $entryIds = $entries->pluck('id')->all();

        // Single query across all requested relation fields
        $pivotRows = DB::table('magna_relations')
            ->whereIn('from_id', $entryIds)
            ->where('from_type', $typeHandle)
            ->whereIn('field', $fieldHandles)
            ->orderBy('sort')
            ->select(['from_id', 'to_id', 'to_type', 'field'])
            ->get();

        // Collect to_ids grouped by to_type for batch loading
        /** @var array<string, list<string>> $byType */
        $byType = [];

        foreach ($pivotRows as $rowRaw) {
            $row = (array) $rowRaw;
            $toType = is_string($row['to_type'] ?? null) ? $row['to_type'] : '';
            $toId = is_string($row['to_id'] ?? null) ? $row['to_id'] : '';
            if ($toType === '' || $toId === '') {
                continue;
            }
            $byType[$toType][] = $toId;
        }

        // One query per distinct target type
        /** @var array<string, Collection<array-key, Entry>> $related */
        $related = [];
        foreach ($byType as $relType => $relIds) {
            $related[$relType] = Entry::type($relType)
                ->whereIn('id', array_unique($relIds))
                ->where('status', EntryStatus::Published->value)
                ->get()
                ->keyBy('id');
        }

        // Build result indexed by field and from_id
        /** @var array<string, array<string, array<int, Entry>>> $result */
        $result = [];

        foreach ($pivotRows as $rowRaw) {
            $row = (array) $rowRaw;
            $field = is_string($row['field'] ?? null) ? $row['field'] : '';
            $fromId = is_string($row['from_id'] ?? null) ? $row['from_id'] : '';
            $toType = is_string($row['to_type'] ?? null) ? $row['to_type'] : '';
            $toId = is_string($row['to_id'] ?? null) ? $row['to_id'] : '';

            if ($field === '' || $fromId === '' || $toType === '' || $toId === '') {
                continue;
            }

            if (! isset($related[$toType])) {
                continue;
            }

            $relEntry = $related[$toType]->get($toId);
            if ($relEntry instanceof Entry) {
                $result[$field][$fromId][] = $relEntry;
            }
        }

        return $result;
    }
}
