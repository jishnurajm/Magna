<?php

declare(strict_types=1);

namespace Magna\Updater;

/**
 * Decoded response from Update Manager's /updates check-in endpoint.
 *
 * Expected shape:
 * {
 *   "core": {"latest_version": "1.2.0", "update_available": true, "changelog_url": "..."},
 *   "plugins": {"acme/forum": {"latest_version": "2.1.0", "update_available": false, "changelog_url": "..."}}
 * }
 */
final class UpdateCheckResult
{
    /**
     * @param  array<string, UpdateEntry>  $plugins  keyed by plugin slug
     * @param  list<NoticeEntry>  $notices
     */
    public function __construct(
        public readonly ?UpdateEntry $core,
        public readonly array $plugins,
        public readonly array $notices = [],
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        $core = is_array($data['core'] ?? null) ? UpdateEntry::fromArray($data['core']) : null;

        $plugins = [];
        $rawPlugins = $data['plugins'] ?? [];
        foreach (is_array($rawPlugins) ? $rawPlugins : [] as $slug => $entry) {
            if (is_string($slug) && is_array($entry)) {
                $plugins[$slug] = UpdateEntry::fromArray($entry);
            }
        }

        $notices = [];
        $rawNotices = $data['notices'] ?? [];
        foreach (is_array($rawNotices) ? $rawNotices : [] as $entry) {
            if (is_array($entry)) {
                $notice = NoticeEntry::fromArray($entry);
                if ($notice !== null) {
                    $notices[] = $notice;
                }
            }
        }

        return new self($core, $plugins, $notices);
    }
}
