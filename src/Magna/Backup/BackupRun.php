<?php

declare(strict_types=1);

namespace Magna\Backup;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Magna\Users\User;

/**
 * @property string $id
 * @property string $type
 * @property string $status
 * @property string|null $disk
 * @property string|null $secondary_disk
 * @property string|null $path
 * @property string|null $secondary_path
 * @property int|null $size_bytes
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property string|null $triggered_by
 * @property string|null $error_message
 * @property mixed $meta
 */
class BackupRun extends Model
{
    use HasUlids;

    public const TYPE_SCHEDULED = 'scheduled';

    public const TYPE_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'status',
        'disk',
        'secondary_disk',
        'path',
        'secondary_path',
        'size_bytes',
        'started_at',
        'finished_at',
        'triggered_by',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'meta' => 'json',
        ];
    }

    /**
     * The admin who triggered a manual run (null for scheduled runs).
     *
     * @return BelongsTo<User, $this>
     */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
