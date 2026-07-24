<?php

declare(strict_types=1);

namespace Magna\Content;

use Magna\Content\Models\ContentTypeRecord;

/**
 * Applies a confirmed schema diff for a content type: registers it, syncs
 * the underlying database table, then persists the schema definition.
 *
 * Extracted from ContentTypeBuilder (the Filament page) so the page itself
 * only handles Livewire/UI state, not registry + schema-sync + persistence.
 */
class ContentTypeApplier
{
    public function __construct(
        private readonly SchemaRegistry $registry,
        private readonly SchemaSyncer $syncer,
    ) {}

    /**
     * @param  array<string, mixed>  $schema
     */
    public function apply(ContentType $type, array $schema, DiffResult $diff, bool $allowDestructive): void
    {
        $this->registry->register($type);
        $this->syncer->sync($diff, $this->registry, $allowDestructive);

        ContentTypeRecord::updateOrCreate(
            ['handle' => $type->handle],
            [
                'display_name' => $type->displayName,
                'is_database_defined' => true,
                'schema' => $schema,
            ],
        );
    }
}
