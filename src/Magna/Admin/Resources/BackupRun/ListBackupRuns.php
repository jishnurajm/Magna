<?php

declare(strict_types=1);

namespace Magna\Admin\Resources\BackupRun;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Magna\Admin\Resources\BackupResource;

class ListBackupRuns extends ListRecords
{
    protected static string $resource = BackupResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
