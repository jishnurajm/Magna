<?php

declare(strict_types=1);

namespace Magna\Backup\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Magna\Backup\BackupRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a completed backup archive — primary destination by default, or
 * the secondary (offsite) copy via ?copy=secondary, so a Stage 7
 * secondary-disk backup is independently downloadable/verifiable, not just
 * assumed to work because the primary does. Unlike MediaServeController
 * (signed URL, no permission check — appropriate for media), a backup
 * archive can contain a full database dump, so this requires the real
 * `backup.manage` permission via route middleware (see
 * BackupServiceProvider::boot()), not just an unguessable link.
 */
class BackupDownloadController extends Controller
{
    public function __invoke(Request $request, BackupRun $backupRun): StreamedResponse
    {
        abort_unless($backupRun->status === BackupRun::STATUS_SUCCESS, 404);

        $useSecondary = $request->query('copy') === 'secondary';
        $diskName = $useSecondary ? $backupRun->secondary_disk : $backupRun->disk;
        $path = $useSecondary ? $backupRun->secondary_path : $backupRun->path;

        abort_unless($diskName !== null && $path !== null, 404);

        $disk = Storage::disk($diskName);

        abort_unless($disk->exists($path), 404);

        return $disk->download($path, basename($path));
    }
}
