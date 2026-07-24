<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Magna\Backup\BackupRun;
use Magna\Backup\Notifications\BackupFailedNotification;
use Magna\Backup\Notifications\BackupSizeWarningNotification;
use Magna\Backup\Notifications\BackupSucceededNotification;
use Magna\Users\User;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// Deliberately no Notification::fake() here — BackupNotificationsTest.php
// covers dispatch/routing logic with the fake, which never actually calls
// toMail(). That's exactly how a real bug slipped through the whole Stage 6
// suite: BackupResource::getUrl() requires an active Filament panel
// context, which a real queue worker process (php artisan queue:work) does
// not have. The Pest process itself has no bound panel either — so
// rendering toMail() for real here is precisely what would have caught
// this before it shipped. Found live, not by a test — these tests exist so
// it can't happen silently again.

it('renders BackupFailedNotification to mail without a Filament panel bound', function (): void {
    $run = BackupRun::create(['type' => BackupRun::TYPE_MANUAL, 'status' => BackupRun::STATUS_FAILED, 'error_message' => 'boom']);
    $user = new User(['email' => 'test@example.com']);

    $mail = (new BackupFailedNotification($run))->toMail($user);

    expect($mail->subject)->toContain('Backup failed');
});

it('renders BackupSucceededNotification to mail without a Filament panel bound', function (): void {
    $run = BackupRun::create(['type' => BackupRun::TYPE_MANUAL, 'status' => BackupRun::STATUS_SUCCESS, 'size_bytes' => 1024]);
    $user = new User(['email' => 'test@example.com']);

    $mail = (new BackupSucceededNotification($run))->toMail($user);

    expect($mail->subject)->toContain('Backup completed');
});

it('renders BackupSizeWarningNotification to mail without a Filament panel bound', function (): void {
    $run = BackupRun::create(['type' => BackupRun::TYPE_MANUAL, 'status' => BackupRun::STATUS_SUCCESS, 'size_bytes' => 1_048_576 * 500]);
    $user = new User(['email' => 'test@example.com']);

    $mail = (new BackupSizeWarningNotification($run, 100))->toMail($user);

    expect($mail->subject)->toContain('size warning');
});
