<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Support\Facades\Schema;
use Magna\Content\Models\ContentTypeRecord;

class SchemaDiffer
{
    public function __construct(
        private readonly TableGenerator $generator,
        private readonly FieldTypeRegistry $fieldTypes,
    ) {}

    public function diff(ContentType $type): DiffResult
    {
        $changes = [];
        $tableName = $type->tableName();

        if (! Schema::hasTable($tableName)) {
            $changes[] = new DiffChange(
                type: DiffChangeType::CreateTable,
                contentTypeHandle: $type->handle,
                column: null,
                destructive: false,
                description: "Create table \"{$tableName}\".",
            );

            return new DiffResult($changes);
        }

        $expectedHandles = array_map(
            fn (Field $f): string => $f->handle,
            $type->columnFields(),
        );

        $liveColumns = $this->generator->dynamicColumns($type);

        foreach ($expectedHandles as $handle) {
            if (! in_array($handle, $liveColumns, true)) {
                $changes[] = new DiffChange(
                    type: DiffChangeType::AddColumn,
                    contentTypeHandle: $type->handle,
                    column: $handle,
                    destructive: false,
                    description: "Add column \"{$handle}\" to \"{$tableName}\".",
                );
            }
        }

        foreach ($liveColumns as $liveCol) {
            if (! in_array($liveCol, $expectedHandles, true)) {
                $changes[] = new DiffChange(
                    type: DiffChangeType::RemoveColumn,
                    contentTypeHandle: $type->handle,
                    column: $liveCol,
                    destructive: true,
                    description: "Remove column \"{$liveCol}\" from \"{$tableName}\" (destructive).",
                );
            }
        }

        // Detect field type changes using the stored schema (driver-independent).
        $storedRecord = ContentTypeRecord::query()->where('handle', $type->handle)->first();
        if ($storedRecord instanceof ContentTypeRecord) {
            $storedType = ContentType::fromArray($storedRecord->schema, $this->fieldTypes);

            foreach ($type->columnFields() as $currentField) {
                if (! in_array($currentField->handle, $liveColumns, true)) {
                    continue; // Already flagged as AddColumn above.
                }

                $storedField = $storedType->getField($currentField->handle);
                if ($storedField === null) {
                    continue;
                }

                $typeChanged = $currentField->type::class !== $storedField->type::class;
                $jsonClassChanged = $currentField->type->isJsonColumn() !== $storedField->type->isJsonColumn();

                if ($typeChanged || $jsonClassChanged) {
                    $changes[] = new DiffChange(
                        type: DiffChangeType::ChangeColumn,
                        contentTypeHandle: $type->handle,
                        column: $currentField->handle,
                        destructive: true,
                        description: "Column \"{$currentField->handle}\" type changed from \"{$storedField->type->typeName()}\" to \"{$currentField->type->typeName()}\" (destructive).",
                    );
                }
            }
        }

        return new DiffResult($changes);
    }

    public function diffAll(SchemaRegistry $registry): DiffResult
    {
        $all = [];
        foreach ($registry->all() as $type) {
            foreach ($this->diff($type)->changes as $change) {
                $all[] = $change;
            }
        }

        return new DiffResult($all);
    }
}
