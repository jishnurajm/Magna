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

// S1-03 regression: the avatar uploader used to accept SVG (via ->image()),
// and the raw file is written to the public disk the instant it's selected —
// before save()'s MediaIngestor sanitization ever runs. SVG is now excluded
// from the accepted file types entirely.
it('rejects an SVG avatar upload', function (): void {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $svg = UploadedFile::fake()->createWithContent(
        'evil.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
    );

    Livewire::test(ProfilePage::class)
        ->fillForm([
            'avatar_path' => $svg,
            'name' => $user->name,
            'email' => $user->email,
        ])
        ->call('save')
        ->assertHasErrors(['data.avatar_path']);

    $user->refresh();
    expect($user->avatar_path)->toBeNull();
});
