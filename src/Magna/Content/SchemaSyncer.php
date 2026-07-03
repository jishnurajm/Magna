<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Support\Facades\DB;
use Magna\Content\Exceptions\DestructiveChangeException;
use Magna\Content\Models\ContentTypeRecord;

class SchemaSyncer
{
    public function __construct(
        private readonly TableGenerator $generator,
        private readonly SchemaDiffer $differ,
    ) {}

    /**
     * Apply a single diff result.
     *
     * @throws DestructiveChangeException when destructive changes exist and $allowDestructive is false
     */
    public function sync(DiffResult $diff, SchemaRegistry $registry, bool $allowDestructive = false): void
    {
        if (! $allowDestructive && $diff->hasDestructive()) {
            $descriptions = implode('; ', array_map(
                fn (DiffChange $c): string => $c->description,
                $diff->destructive(),
            ));
            throw new DestructiveChangeException(
                "Destructive schema changes require --allow-destructive: {$descriptions}",
            );
        }

        foreach ($diff->changes as $change) {
            $type = $registry->get($change->contentTypeHandle);
            if ($type === null) {
                continue;
            }

            $this->applyChange($change, $type);
        }

        // Upsert content_types records so the differ can detect future type changes.
        foreach ($registry->all() as $type) {
            ContentTypeRecord::updateOrCreate(
                ['handle' => $type->handle],
                [
                    'display_name' => $type->displayName,
                    'is_database_defined' => false,
                    'schema' => $type->toArray(),
                ],
            );
        }
    }

    /**
     * Diff and sync every registered content type.
     *
     * @throws DestructiveChangeException
     */
    public function syncAll(SchemaRegistry $registry, bool $allowDestructive = false): DiffResult
    {
        $diff = $this->differ->diffAll($registry);

        if ($diff->isEmpty()) {
            return $diff;
        }

        // Wrap in a transaction for databases that support transactional DDL (Postgres).
        // On SQLite, DDL is auto-committed, so this is best-effort.
        if (DB::getDriverName() !== 'sqlite') {
            DB::transaction(function () use ($diff, $registry, $allowDestructive): void {
                $this->sync($diff, $registry, $allowDestructive);
            });
        } else {
            $this->sync($diff, $registry, $allowDestructive);
        }

        return $diff;
    }

    private function applyChange(DiffChange $change, ContentType $type): void
    {
        match ($change->type) {
            DiffChangeType::CreateTable => $this->generator->createTable($type),
            DiffChangeType::AddColumn => $this->applyAddColumn($change, $type),
            DiffChangeType::RemoveColumn => $this->applyRemoveColumn($change, $type),
            DiffChangeType::ChangeColumn => $this->applyChangeColumn($change, $type),
        };
    }

    private function applyAddColumn(DiffChange $change, ContentType $type): void
    {
        $field = $type->getField($change->column ?? '');
        if ($field === null) {
            return;
        }

        $this->generator->addColumn($type, $field);
    }

    private function applyRemoveColumn(DiffChange $change, ContentType $type): void
    {
        if ($change->column === null) {
            return;
        }

        $this->generator->dropColumn($type, $change->column);
    }

    private function applyChangeColumn(DiffChange $change, ContentType $type): void
    {
        // Drop then re-add is the safest cross-driver approach for type changes.
        if ($change->column === null) {
            return;
        }

        $this->generator->dropColumn($type, $change->column);

        $field = $type->getField($change->column);
        if ($field === null) {
            return;
        }

        $this->generator->addColumn($type, $field);
    }
}
