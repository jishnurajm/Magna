<?php

declare(strict_types=1);

namespace Magna\Backup\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Magna\Backup\BackupRun;
use Magna\Backup\Notifications\Concerns\ResolvesHistoryUrl;

/**
 * Alert-only, not a failure — an oversized backup usually means media or the
 * database is growing unpruned, not that anything actually went wrong. Sent
 * only when BackupSettings.notify_emails is configured, same gating as
 * BackupSucceededNotification (this is an FYI, not a "something is broken"
 * signal the way a failed run is).
 */
class BackupSizeWarningNotification extends Notification
{
    use ResolvesHistoryUrl;

    public function __construct(private readonly BackupRun $run, private readonly int $thresholdMb) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sizeMb = round(($this->run->size_bytes ?? 0) / 1_048_576, 1);

        $message = (new MailMessage)
            ->subject('Backup size warning — '.Config::string('app.name', 'Magna CMS'))
            ->line("A {$this->run->type} backup completed at {$sizeMb} MB, exceeding the configured warning threshold of {$this->thresholdMb} MB.")
            ->line('This usually means media or the database is growing without being pruned — worth checking.');

        $url = $this->historyUrl();

        return $url !== null ? $message->action('View backup history', $url) : $message;
    }
}
