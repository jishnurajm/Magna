<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Magna\Admin\Pages\ProfilePage;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('magna'));
});

it('renders the profile page with a photo upload field', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(ProfilePage::class)
        ->assertOk()
        ->assertSee('Profile photo')
        ->assertSee('Full name');
});

it('saves an uploaded avatar and exposes it as the Filament avatar url', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(ProfilePage::class)
        ->fillForm([
            'avatar_path' => UploadedFile::fake()->image('me.png', 200, 200),
            'name' => $user->name,
            'email' => $user->email,
        ])
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->avatar_path)->not->toBeNull()
        ->and($user->getFilamentAvatarUrl())->toContain($user->avatar_path);
    Storage::disk('public')->assertExists($user->avatar_path);
});

it('returns no avatar url when none is set', function (): void {
    $user = User::factory()->create(['avatar_path' => null]);

    expect($user->getFilamentAvatarUrl())->toBeNull();
});
