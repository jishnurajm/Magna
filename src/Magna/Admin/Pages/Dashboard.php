<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Magna\Admin\Widgets\EntryCounts;
use Magna\Admin\Widgets\RecentActivity;
use Magna\Users\User;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -1;

    protected string $view = 'magna::admin.dashboard';

    /** @return list<class-string<Widget>> */
    public function getWidgets(): array
    {
        // All widgets registered on the panel — includes core widgets AND any
        // contributed by plugins via RegistersDashboardWidgets, so new plugins
        // appear automatically with no dashboard changes.
        /** @var list<class-string<Widget>> $all */
        $all = array_values(Filament::getCurrentPanel()?->getWidgets() ?? []);

        if ($all === []) {
            $all = [EntryCounts::class, RecentActivity::class];
        }

        // Order by each widget's declared static $sort (Filament convention), so
        // a plugin widget lands in a sensible position rather than at the bottom.
        usort($all, fn (string $a, string $b): int => $this->widgetSort($a) <=> $this->widgetSort($b));

        /** @var User|null $user */
        $user = auth()->user();
        $savedOrder = $user?->widget_order ?? [];

        if (empty($savedOrder)) {
            return $all;
        }

        // Re-order by saved preference; append any newly added widgets at the end.
        $indexed = collect($all)->keyBy(fn (string $class): string => class_basename($class));

        $sorted = collect($savedOrder)
            ->map(fn (string $key): ?string => $indexed->get($key))
            ->filter()
            ->values()
            ->all();

        $missing = array_values(
            array_filter($all, fn (string $class): bool => ! in_array(class_basename($class), $savedOrder, true))
        );

        return array_merge($sorted, $missing);
    }

    /** Read a widget's declared static $sort (Filament convention); default last. */
    private function widgetSort(string $class): int
    {
        try {
            $reflection = new \ReflectionClass($class);
            if ($reflection->hasProperty('sort')) {
                $property = $reflection->getProperty('sort');
                $value = $property->getValue();

                return is_int($value) ? $value : 999;
            }
        } catch (\Throwable) {
            // Fall through to the default.
        }

        return 999;
    }

    /** @return list<class-string<Widget>> */
    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    public function getColumns(): int|array
    {
        return 2;
    }

    /**
     * Called by the dashboard blade after the user drags widgets into a new order.
     *
     * @param  list<string>  $order  Array of class_basename keys, e.g. ['RecentActivity', 'EntryCounts']
     */
    public function reorderWidgets(array $order): void
    {
        /** @var User|null $user */
        $user = auth()->user();
        $user?->update(['widget_order' => $order]);
    }
}
