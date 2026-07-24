<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Magna\Auth\Role;
use Magna\Media\Media;
use Magna\Media\MediaFolder;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function mediaAdminToken(): string
{
    $role = Role::factory()->create();
    $role->grant('media.view', 'media.upload', 'media.delete');

    $user = User::factory()->create();
    $user->assignRole($role);

    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

function mediaViewerToken(): string
{
    $role = Role::factory()->create();
    $role->grant('media.view');

    $user = User::factory()->create();
    $user->assignRole($role);

    $result = $user->createToken('mgmt', ['management'], now()->addDay());
    $result->accessToken->forceFill(['scope' => 'management'])->save();

    return $result->plainTextToken;
}

// ── File upload ───────────────────────────────────────────────────────────────

it('uploads a media file and returns 201', function (): void {
    Storage::fake('public');

    $token = mediaAdminToken();
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $response = $this->withToken($token)
        ->postJson('/api/v1/manage/media', [
            'file' => $file,
            'alt' => 'A test photo',
            'title' => 'Test Photo',
        ])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'original_filename', 'mime_type', 'url']]);

    expect($response->json('data.original_filename'))->toBe('photo.jpg')
        ->and(Media::count())->toBe(1);
});

it('returns 422 when no file is uploaded', function (): void {
    $token = mediaAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/manage/media', [])
        ->assertStatus(422);
});

it('returns 403 when viewer tries to upload', function (): void {
    Storage::fake('public');

    $token = mediaViewerToken();
    $file = UploadedFile::fake()->image('photo.jpg');

    $this->withToken($token)
        ->postJson('/api/v1/manage/media', ['file' => $file])
        ->assertForbidden();
});

// ── Show ──────────────────────────────────────────────────────────────────────

it('shows a media item', function (): void {
    Storage::fake('public');

    $token = mediaAdminToken();

    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/test.jpg',
        'filename' => 'test.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $this->withToken($token)
        ->getJson('/api/v1/manage/media/'.$media->id)
        ->assertOk()
        ->assertJsonPath('data.id', $media->id);
});

it('returns 404 for missing media', function (): void {
    $token = mediaAdminToken();

    $this->withToken($token)
        ->getJson('/api/v1/manage/media/'.str_repeat('0', 26))
        ->assertNotFound();
});

// ── Delete ────────────────────────────────────────────────────────────────────

it('deletes a media item', function (): void {
    Storage::fake('public');

    $token = mediaAdminToken();

    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/test.jpg',
        'filename' => 'test.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $this->withToken($token)
        ->deleteJson('/api/v1/manage/media/'.$media->id)
        ->assertNoContent();

    expect(Media::find($media->id))->toBeNull();
});

it('returns 403 when viewer tries to delete', function (): void {
    $token = mediaViewerToken();

    $media = Media::create([
        'disk' => 'public',
        'path' => 'media/test.jpg',
        'filename' => 'test.jpg',
        'original_filename' => 'test.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $this->withToken($token)
        ->deleteJson('/api/v1/manage/media/'.$media->id)
        ->assertForbidden();
});

// ── Folders ───────────────────────────────────────────────────────────────────

it('creates a folder', function (): void {
    $token = mediaAdminToken();

    $this->withToken($token)
        ->postJson('/api/v1/manage/media/folders', ['name' => 'Images'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Images');
});

it('lists folders', function (): void {
    $token = mediaAdminToken();

    MediaFolder::create(['name' => 'Photos', 'path' => 'photos']);
    MediaFolder::create(['name' => 'Videos', 'path' => 'videos']);

    $response = $this->withToken($token)
        ->getJson('/api/v1/manage/media/folders')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('deletes a folder', function (): void {
    $token = mediaAdminToken();

    $folder = MediaFolder::create(['name' => 'Old Stuff', 'path' => 'old-stuff']);

    $this->withToken($token)
        ->deleteJson('/api/v1/manage/media/folders/'.$folder->id)
        ->assertNoContent();

    expect(MediaFolder::find($folder->id))->toBeNull();
});

it('returns 403 when viewer tries to create a folder', function (): void {
    $token = mediaViewerToken();

    $this->withToken($token)
        ->postJson('/api/v1/manage/media/folders', ['name' => 'New'])
        ->assertForbidden();
});
