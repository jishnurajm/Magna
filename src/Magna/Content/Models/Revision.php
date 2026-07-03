<?php

declare(strict_types=1);

namespace Magna\Content\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $entry_type
 * @property string $entry_id
 * @property array<string, mixed> $payload
 * @property string|null $author_id
 * @property Carbon $created_at
 */
class Revision extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'magna_revisions';

    protected $fillable = [
        'entry_type',
        'entry_id',
        'payload',
        'author_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Revisions are append-only and cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Revisions are append-only and cannot be deleted.');
    }
}
