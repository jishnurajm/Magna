<?php

declare(strict_types=1);

namespace Magna\Backup\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Magna\Backup\BackupRun;
use Magna\Backup\Notifications\Concerns\ResolvesHistoryUrl;

/**
 * Sent on every failed backup run, regardless of BackupSettings.notify_emails
 * being configured — see RunBackupJob for the recipient-resolution rule
 * (configured list, or every super_admin if that list is empty).
 */
class BackupFailedNotification extends Notification
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
            ->subject('Backup failed — '.Config::string('app.name', 'Magna CMS'))
            ->error()
            ->line('A '.$this->run->type.' backup run failed.')
            ->line('Started: '.($this->run->started_at?->toDayDateTimeString() ?? 'unknown'))
            ->line('Error: '.($this->run->error_message ?? 'unknown error'));

        $url = $this->historyUrl();

        return $url !== null ? $message->action('View backup history', $url) : $message;
    }
}
