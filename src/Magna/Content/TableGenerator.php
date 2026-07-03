<?php

declare(strict_types=1);

namespace Magna\Content;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TableGenerator
{
    private const FIXED_COLUMNS = [
        'id', 'status', 'locale', 'published_at', 'author_id', 'draft_of', 'created_at', 'updated_at',
    ];

    public function createTable(ContentType $type): void
    {
        $tableName = $type->tableName();

        Schema::create($tableName, function (Blueprint $table) use ($type): void {
            $table->ulid('id')->primary();
            $table->string('status', 20)->default('draft');
            $table->string('locale', 10)->default('');
            $table->timestamp('published_at')->nullable();
            $table->char('author_id', 26)->nullable();
            $table->char('draft_of', 26)->nullable()->index();
            $table->timestamps();

            foreach ($type->columnFields() as $field) {
                $field->type->addColumn($table, $field->handle);
            }
        });

        $this->addGinIndexes($type);
    }

    public function dropTable(ContentType $type): void
    {
        Schema::dropIfExists($type->tableName());
    }

    public function addColumn(ContentType $type, Field $field): void
    {
        Schema::table($type->tableName(), function (Blueprint $table) use ($field): void {
            $field->type->addColumn($table, $field->handle);
        });

        if ($field->type->isJsonColumn()) {
            $this->addGinIndex($type->tableName(), $type->handle, $field->handle);
        }
    }

    public function dropColumn(ContentType $type, string $column): void
    {
        Schema::table($type->tableName(), function (Blueprint $table) use ($column): void {
            $table->dropColumn($column);
        });
    }

    /** @return list<string> */
    public function dynamicColumns(ContentType $type): array
    {
        if (! Schema::hasTable($type->tableName())) {
            return [];
        }

        return array_values(array_filter(
            Schema::getColumnListing($type->tableName()),
            fn (string $col): bool => ! in_array($col, self::FIXED_COLUMNS, true),
        ));
    }

    private function addGinIndexes(ContentType $type): void
    {
        foreach ($type->jsonColumnHandles() as $handle) {
            $this->addGinIndex($type->tableName(), $type->handle, $handle);
        }
    }

    private function addGinIndex(string $tableName, string $typeHandle, string $column): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $indexName = 'idx_'.$typeHandle.'_'.$column.'_gin';
        DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$tableName} USING gin ({$column})");
    }
}
