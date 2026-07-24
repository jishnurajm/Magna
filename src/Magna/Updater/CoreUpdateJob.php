<?php

declare(strict_types=1);

namespace Magna\Updater;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued wrapper around {@see CoreUpdater} so applying an update runs in the
 * background and the admin request returns immediately — same shape as
 * Magna\Marketplace\InstallPluginJob.
 */
class CoreUpdateJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Generous ceiling — download + overlay + migrate can be slow. */
    public int $timeout = 1800;

    public function __construct(
        public readonly string $targetVersion,
        public readonly string $zipUrl,
        public readonly bool $force = false,
    ) {}

    public function handle(CoreUpdater $updater): void
    {
        if ($updater->apply($this->targetVersion, $this->zipUrl, $this->force) === CoreUpdateState::Queued) {
            $this->release(15);
        }
    }
}
