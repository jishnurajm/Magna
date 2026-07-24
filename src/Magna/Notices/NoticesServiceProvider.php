<?php

declare(strict_types=1);

namespace Magna\Notices;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Magna\Notices\Livewire\DashboardNoticesBanner;

/**
 * Dashboard banners sent from the Marketplace plugin's Announcements screen
 * — core, not a plugin, same reasoning as Updater/AccountCentre: a site
 * should always be able to see what the CMS owner has broadcast, even with
 * every optional plugin disabled. See docs/dashboard-notices-plan.md.
 */
class NoticesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/views', 'magna');

        Livewire::component('magna-dashboard-notices', DashboardNoticesBanner::class);
    }
}
