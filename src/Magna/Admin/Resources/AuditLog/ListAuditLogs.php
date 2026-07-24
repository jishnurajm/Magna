<?php

declare(strict_types=1);

namespace Magna\Admin\Resources\AuditLog;

use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Magna\Admin\Resources\AuditLogResource;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
