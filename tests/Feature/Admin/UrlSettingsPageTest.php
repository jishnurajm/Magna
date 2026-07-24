<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Magna\Admin\Pages\UrlSettingsPage;
use Magna\Auth\Role;
use Magna\Plugins\PluginRecord;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('magna'));
});

function urlSettingsSuperAdmin(): User
{
    $role = Role::factory()->create([
        'handle' => 'super_admin',
        'name' => 'Super Admin',
        'is_super_admin' => true,
    ]);
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('shows the install-Magna-Pages notice when the plugin is absent', function (): void {
    $this->actingAs(urlSettingsSuperAdmin());

    Livewire::test(UrlSettingsPage::class)
        ->assertOk()
        ->assertSee('Frontend configuration requires Magna Pages')
        ->assertSee('CDN URL')
        ->assertDontSee('Dashboard URL');
});

it('shows the frontend URL fields when Magna Pages is installed', function (): void {
    PluginRecord::create([
        'name' => 'magna/pages',
        'display_name' => 'Magna Pages',
        'version' => '1.0.0',
        'enabled' => true,
        'base_path' => sys_get_temp_dir().'/magna-pages',
        'manifest' => ['name' => 'magna/pages'],
    ]);

    $this->actingAs(urlSettingsSuperAdmin());

    Livewire::test(UrlSettingsPage::class)
        ->assertOk()
        ->assertSee('Frontend URL')
        ->assertSee('Preview base URL')
        ->assertDontSee('Frontend configuration requires Magna Pages');
});
