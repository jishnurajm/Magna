<?php

declare(strict_types=1);

namespace Magna\Admin\Pages;

use Filament\Pages\Page;
use Magna\AccountCentre\AccountCentreClient;
use Magna\AccountCentre\AccountCentreSettings;

/**
 * This site's connection to a Magna Account (managemagna.jrstudios.dev) — not
 * a CMS admin login. Lives in the System nav group, directly below Plugins,
 * since connecting an account is what will gate plugin/theme installs later
 * (see docs/account-centre-plan.md).
 */
class AccountCentrePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'magna-mark';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Magna Account';

    protected static ?string $title = 'Magna Account';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'account-centre';

    protected string $view = 'magna::admin.account-centre';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.view') ?? false;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $settings = AccountCentreSettings::get();

        $otherSites = [];
        if ($settings->connected && $settings->token !== null) {
            $otherSites = array_values(array_filter(
                app(AccountCentreClient::class)->sites($settings->token),
                fn (array $s): bool => ! ($s['is_this_site'] ?? false),
            ));
        }

        return [
            'connected' => $settings->connected,
            'accountName' => $settings->accountName,
            'accountEmail' => $settings->accountEmail,
            'connectedAt' => $settings->connectedAt,
            'otherSites' => $otherSites,
        ];
    }
}
