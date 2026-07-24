<?php

declare(strict_types=1);

namespace Magna\Updater;

final class UpdateEntry
{
    public function __construct(
        public readonly ?string $latestVersion,
        public readonly bool $updateAvailable,
        public readonly ?string $changelogUrl,
        public readonly ?string $downloadUrl = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            latestVersion: is_string($data['latest_version'] ?? null) ? $data['latest_version'] : null,
            updateAvailable: (bool) ($data['update_available'] ?? false),
            changelogUrl: is_string($data['changelog_url'] ?? null) ? $data['changelog_url'] : null,
            downloadUrl: is_string($data['zip_url'] ?? null) ? $data['zip_url'] : null,
        );
    }
}
