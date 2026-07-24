<?php

declare(strict_types=1);

namespace Magna\Backup\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Magna\Backup\BackupRun;
use Magna\Backup\Notifications\Concerns\ResolvesHistoryUrl;

/**
 * Sent only when BackupSettings.notify_emails is non-empty — success alerts
 * are opt-in, unlike failure alerts (see BackupFailedNotification).
 */
class BackupSucceededNotification extends Notification
{
    use ResolvesHistoryUrl;

    public function __construct(private readonly BackupRun $run) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Backup completed — '.Config::string('app.name', 'Magna CMS'))
            ->success()
            ->line('A '.$this->run->type.' backup run completed successfully.')
            ->line('Started: '.($this->run->started_at?->toDayDateTimeString() ?? 'unknown'))
            ->line('Size: '.$this->formatBytes($this->run->size_bytes));

        $url = $this->historyUrl();

        return $url !== null ? $message->action('View backup history', $url) : $message;
    }

    private function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'unknown';
        }

        return match (true) {
            $bytes >= 1_073_741_824 => number_format($bytes / 1_073_741_824, 2).' GB',
            $bytes >= 1_048_576 => number_format($bytes / 1_048_576, 1).' MB',
            $bytes >= 1_024 => number_format($bytes / 1_024, 0).' KB',
            default => number_format($bytes).' B',
        };
    }
}
