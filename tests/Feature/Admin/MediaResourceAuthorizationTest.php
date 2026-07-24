<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Magna\Admin\Resources\Media\CreateMedia;
use Magna\Admin\Resources\MediaResource;
use Magna\Auth\Role;
use Magna\Media\Media;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('magna'));
});

// ── S1-01: MediaResource previously had zero authorization ───────────────────

it('denies view/create/delete with zero media permissions', function (): void {
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $this->actingAs($user);

    $media = Media::factory()->create();

    expect(MediaResource::canViewAny())->toBeFalse()
        ->and(MediaResource::canCreate())->toBeFalse()
        ->and(MediaResource::canEdit($media))->toBeFalse()
        ->and(MediaResource::canDelete($media))->toBeFalse();
});

it('grants access matching the specific media.* permission held', function (): void {
    $role = Role::factory()->create();
    $role->grant('media.view');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $media = Media::factory()->create();

    expect(MediaResource::canViewAny())->toBeTrue()
        ->and(MediaResource::canCreate())->toBeFalse()
        ->and(MediaResource::canDelete($media))->toBeFalse();
});

it('super admin has full MediaResource access without explicit grants', function (): void {
    $role = Role::factory()->create(['is_super_admin' => true]);
    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $media = Media::factory()->create();

    expect(MediaResource::canViewAny())->toBeTrue()
        ->and(MediaResource::canCreate())->toBeTrue()
        ->and(MediaResource::canDelete($media))->toBeTrue();
});

// ── S1-03: admin upload must go through MediaIngestor's sanitization pipeline ─

it('sanitizes an SVG uploaded through the admin panel instead of storing it raw', function (): void {
    Storage::fake('public');

    $role = Role::factory()->create();
    $role->grant('media.upload', 'media.view');

    $user = User::factory()->create(['two_factor_confirmed_at' => now()]);
    $user->assignRole($role);
    $this->actingAs($user);

    $svg = UploadedFile::fake()->createWithContent(
        'evil.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script><rect width="10" height="10"/></svg>',
    );

    Livewire::test(CreateMedia::class)
        ->fillForm(['file' => $svg])
        ->call('create')
        ->assertHasNoErrors();

    $media = Media::query()->latest('created_at')->first();
    expect($media)->not->toBeNull();

    // The stored copy must be MediaIngestor's sanitized output, not the raw upload.
    $stored = (string) Storage::disk('public')->get($media->path);
    expect($stored)->not->toContain('<script')
        ->and($stored)->toContain('<rect');

    // The raw pre-sanitization upload Filament wrote before the create hook
    // ran must not be left behind on the public disk.
    foreach (Storage::disk('public')->allFiles('media') as $file) {
        expect(Storage::disk('public')->get($file))->not->toContain('<script');
    }
});
