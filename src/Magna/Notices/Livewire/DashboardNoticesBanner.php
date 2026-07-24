<?php

declare(strict_types=1);

namespace Magna\Notices\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Magna\Admin\Pages\SystemInfoPage;
use Magna\Notices\DashboardNotice;

/**
 * Pinned above the dashboard's widget grid — not a widget itself, since a
 * notice banner needs to always sit at the top, non-draggable, with its own
 * per-notice dismiss state, none of which the reorderable masonry grid
 * (Dashboard::getWidgets()) is built for. Only ever shows notices sent from
 * the Marketplace plugin's Announcements screen — see
 * docs/dashboard-notices-plan.md.
 *
 * Shows at most ONE notice at a time (DashboardNotice::toShow(), priority
 * system_upgrade > welcome > announcement) — stacking banners was
 * explicitly ruled out as "it will make a mess." Dismissing the shown one
 * naturally reveals the next-highest-priority one on the following render.
 */
class DashboardNoticesBanner extends Component
{
    public function dismiss(int $id): void
    {
        DashboardNotice::query()->where('id', $id)->update(['dismissed_at' => now()]);
    }

    public function render(): View
    {
        return view('magna::livewire.dashboard-notices-banner', [
            'notice' => DashboardNotice::toShow(),
            'systemInfoUrl' => SystemInfoPage::getUrl(),
        ]);
    }
}
