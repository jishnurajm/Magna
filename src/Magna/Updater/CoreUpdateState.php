<?php

declare(strict_types=1);

namespace Magna\Updater;

/** Lifecycle of a core update apply, surfaced to the UI. Mirrors Magna\Marketplace\InstallState. */
enum CoreUpdateState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
