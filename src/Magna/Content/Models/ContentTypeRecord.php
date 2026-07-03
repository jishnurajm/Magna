<?php

declare(strict_types=1);

namespace Magna\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
}
