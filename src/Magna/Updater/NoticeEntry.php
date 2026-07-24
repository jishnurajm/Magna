<?php

declare(strict_types=1);

namespace Magna\Updater;

/** One dashboard notice as returned by the check-in endpoint's "notices" array. */
final class NoticeEntry
{
    public function __construct(
        public readonly int $id,
        public readonly string $category,
        public readonly ?string $categoryDescription,
        public readonly ?string $imageUrl,
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $linkGithub = null,
        public readonly ?string $linkDocs = null,
        public readonly ?string $linkBlog = null,
        public readonly ?string $linkCommunity = null,
        public readonly ?string $linkThemes = null,
        public readonly ?string $linkPlugins = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        if (! isset($data['id'], $data['category'], $data['title'], $data['description'])) {
            return null;
        }

        $str = static fn (string $key): ?string => is_string($data[$key] ?? null) ? $data[$key] : null;

        // These render as `href`/`src` verbatim in dashboard-notices-banner.blade.php,
        // Update Manager's response is otherwise-untrusted third-party-reachable
        // content, and Blade's `{{ }}` escaping stops attribute-breakout but does
        // nothing about a `javascript:`/`data:` scheme executing on click. Reject
        // anything that isn't a plain http(s) URL rather than pass it through.
        $url = static function (string $key) use ($data): ?string {
            $value = $data[$key] ?? null;
            if (! is_string($value) || $value === '') {
                return null;
            }

            return in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true) ? $value : null;
        };

        return new self(
            id: (int) $data['id'],
            category: (string) $data['category'],
            categoryDescription: $str('category_description'),
            imageUrl: $url('image_url'),
            title: (string) $data['title'],
            description: (string) $data['description'],
            linkGithub: $url('link_github'),
            linkDocs: $url('link_docs'),
            linkBlog: $url('link_blog'),
            linkCommunity: $url('link_community'),
            linkThemes: $url('link_themes'),
            linkPlugins: $url('link_plugins'),
        );
    }
}
