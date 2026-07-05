<?php

declare(strict_types=1);

namespace Magna\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Magna\Content\Entry;
use Magna\Content\SchemaRegistry;

class EntryCounts extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Render a skeleton immediately, then load counts in a deferred request so
    // the dashboard shell appears instantly rather than blocking on queries.
    protected static bool $isLazy = true;

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        /** @var SchemaRegistry $registry */
        $registry = app(SchemaRegistry::class);

        $stats = [];

        foreach ($registry->all() as $type) {
            try {
                $count = Entry::type($type->handle)->count();
            } catch (\Throwable) {
                // Table may not exist yet for new types; skip gracefully.
                $count = 0;
            }

            $stats[] = Stat::make($type->displayName, (string) $count)
                ->icon('heroicon-o-document-text')
                ->color('primary');
        }

        if ($stats === []) {
            $stats[] = Stat::make('Content types', '0')
                ->icon('heroicon-o-document-text')
                ->description('No content types registered yet.')
                ->color('gray');
        }

        return $stats;
    }
}
