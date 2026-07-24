<?php

declare(strict_types=1);

namespace Magna\Marketplace;

/** Lifecycle of a marketplace install, surfaced to the UI. */
enum InstallState: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
