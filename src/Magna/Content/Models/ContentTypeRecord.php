<?php

declare(strict_types=1);

namespace Magna\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Magna\Content\SchemaRegistry;

/**
 * @property string $id
 * @property string $handle
 * @property string $display_name
 * @property bool $is_database_defined
 * @property array<string, mixed> $schema
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContentTypeRecord extends Model
{
    use HasUlids;

    protected $table = 'content_types';

    protected $fillable = [
        'handle',
        'display_name',
        'is_database_defined',
        'schema',
    ];

    protected function casts(): array
    {
        return [
            'is_database_defined' => 'boolean',
            'schema' => 'array',
        ];
    }

    /**
     * Stage 13 (Stage 12 caching follow-up): SchemaRegistry::loadFromDatabase()
     * caches this table's rows to avoid re-querying+re-parsing on every
     * single request (Stage 12 flagged this as an unoptimized repeated
     * cost). Invalidating here — via a model event on every write path,
     * rather than a Cache::forget() call scattered across each caller
     * (ContentTypeController::store/update, PluginManager's plugin
     * content-type persist/deregister) — is the one place that's
     * guaranteed correct regardless of which caller wrote the change, so a
     * stale cached schema after an edit (a correctness bug, not just a
     * perf one) can't creep back in via a future write path that forgets
     * to invalidate explicitly.
     */
    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(SchemaRegistry::CONTENT_TYPES_CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(SchemaRegistry::CONTENT_TYPES_CACHE_KEY));
    }
}
