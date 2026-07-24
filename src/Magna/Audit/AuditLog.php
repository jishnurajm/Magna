<?php

declare(strict_types=1);

namespace Magna\Audit;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Magna\Users\User;

/**
 * Append-only audit log. Entries cannot be updated or deleted at the Eloquent
 * level — any attempt throws a LogicException.
 *
 * @property string $id
 * @property string $action
 * @property string|null $actor_id
 * @property string|null $actor_type
 * @property string|null $ip
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property mixed $before
 * @property mixed $after
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    use HasUlids;

    const UPDATED_AT = null;

    protected $fillable = [
        'action',
        'actor_id',
        'actor_type',
        'ip',
        'subject_type',
        'subject_id',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'json',
            'after' => 'json',
            'created_at' => 'datetime',
        ];
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Audit log entries are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Audit log entries are immutable and cannot be deleted.');
    }

    /**
     * The user who performed the action (nullable — system events have no actor).
     *
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public static function record(
        string $action,
        ?string $actorId = null,
        ?string $actorType = null,
        ?string $ip = null,
        ?Model $subject = null,
        mixed $before = null,
        mixed $after = null,
    ): self {
        $subjectId = null;

        if ($subject !== null) {
            $key = $subject->getKey();
            $subjectId = match (true) {
                is_string($key) => $key,
                is_int($key) => (string) $key,
                default => null,
            };
        }

        $log = new self([
            'action' => $action,
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'ip' => $ip,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subjectId,
            'before' => $before,
            'after' => $after,
        ]);

        $log->save();

        return $log;
    }
}
