<?php

declare(strict_types=1);

namespace Magna\Updater;

use Illuminate\Database\Eloquent\Model;
use Magna\Admin\Notifications\NotificationRecipients;

/**
 * Fires a dashboard notice for a just-updateOrCreate'd record, but only the
 * first time it's seen (new row) or the moment its watched fields actually
 * change — a repeat check-in with nothing new never re-fires.
 *
 * Extracted from UpdateCheckClient::persist()/syncNotices(), which each
 * repeated this same "wasRecentlyCreated || wasChanged(...)" dedup shape
 * inline, mixing it with the updateOrCreate persistence itself.
 */
class UpdateChangeNotifier
{
    /** @param  list<string>  $watchedFields */
    public function notifyIfChanged(Model $record, array $watchedFields, string $title, string $body, string $status = 'info'): void
    {
        if ($record->wasRecentlyCreated || $record->wasChanged($watchedFields)) {
            NotificationRecipients::notifyDashboard($title, $body, $status);
        }
    }
}
