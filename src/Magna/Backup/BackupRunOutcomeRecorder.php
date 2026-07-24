<?php

declare(strict_types=1);

namespace Magna\Backup;

use Magna\Audit\AuditLog;
use Throwable;

/**
 * Records the terminal state of a backup run: updates the BackupRun row and
 * writes the matching audit-log entry. Extracted from RunBackupJob::handle()
 * so the job itself only orchestrates (lock, run, notify), not persistence.
 */
class BackupRunOutcomeRecorder
{
    public function succeeded(BackupRun $run, BackupResult $result, ?string $actorId): void
    {
        $run->update([
            'status' => BackupRun::STATUS_SUCCESS,
            'disk' => $result->disk,
            'path' => $result->path,
            'secondary_disk' => $result->secondaryDisk,
            'secondary_path' => $result->secondaryPath,
            'size_bytes' => $result->sizeBytes,
            'finished_at' => now(),
        ]);

        AuditLog::record(
            action: 'backup.completed',
            actorId: $actorId,
            actorType: $actorId !== null ? 'user' : 'system',
            subject: $run,
            after: [
                'disk' => $result->disk,
                'path' => $result->path,
                'secondary_disk' => $result->secondaryDisk,
                'size_bytes' => $result->sizeBytes,
            ],
        );
    }

    public function failed(BackupRun $run, Throwable $e, ?string $actorId): void
    {
        $run->update([
            'status' => BackupRun::STATUS_FAILED,
            'error_message' => $e->getMessage(),
            'finished_at' => now(),
        ]);

        AuditLog::record(
            action: 'backup.failed',
            actorId: $actorId,
            actorType: $actorId !== null ? 'user' : 'system',
            subject: $run,
            after: ['error_message' => $e->getMessage()],
        );
    }
}
