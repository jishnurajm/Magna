<?php

declare(strict_types=1);

namespace Magna\Admin\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Collection;
use Magna\Users\User;

/**
 * Shared recipient resolution for admin bell notifications — same query
 * Magna\Backup\Jobs\RunBackupJob already used for its own failure-alert
 * fallback, pulled out so every emitter (backup, updates, announcements,
 * system errors) targets the same audience the same way instead of each
 * repeating the whereHas() query independently.
 */
class NotificationRecipients
{
    /** @return Collection<int, User> */
    public static function superAdmins(): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('is_super_admin', true))
            ->get();
    }

    /**
     * Pushes one bell notification to every super_admin. Uses notifyNow(),
     * not sendToDatabase(): Filament\Notifications\DatabaseNotification
     * implements ShouldQueue, so sendToDatabase() only enqueues a job — a
     * real gap found live in Magna\Backup\Jobs\RunBackupJob before this
     * helper existed. notifyNow() bypasses the queue and writes the row
     * immediately, with no worker dependency.
     */
    public static function notifyDashboard(string $title, string $body, string $status = 'info'): void
    {
        foreach (self::superAdmins() as $admin) {
            $notification = FilamentNotification::make()->title($title)->body($body);

            match ($status) {
                'success' => $notification->success(),
                'danger' => $notification->danger(),
                'warning' => $notification->warning(),
                default => $notification->info(),
            };

            $admin->notifyNow($notification->toDatabase());
        }
    }
}
