<?php

declare(strict_types=1);

use Magna\Updater\NoticeEntry;

// dashboard-notices-banner.blade.php renders image_url/link_* as `src`/`href`
// verbatim. Blade's {{ }} escaping stops attribute-breakout but not a
// javascript:/data: scheme executing on click — NoticeEntry::fromArray must
// reject anything that isn't a plain http(s) URL rather than pass it through.
it('keeps http(s) notice links and image url', function (): void {
    $notice = NoticeEntry::fromArray([
        'id' => 1,
        'category' => 'welcome',
        'title' => 'Hi',
        'description' => 'Welcome',
        'image_url' => 'https://example.test/banner.png',
        'link_github' => 'https://github.com/magna-cms/magna',
        'link_docs' => 'http://docs.example.test',
    ]);

    expect($notice)->not->toBeNull()
        ->and($notice->imageUrl)->toBe('https://example.test/banner.png')
        ->and($notice->linkGithub)->toBe('https://github.com/magna-cms/magna')
        ->and($notice->linkDocs)->toBe('http://docs.example.test');
});

it('drops a javascript: scheme link instead of passing it through', function (): void {
    $notice = NoticeEntry::fromArray([
        'id' => 2,
        'category' => 'welcome',
        'title' => 'Hi',
        'description' => 'Welcome',
        'link_github' => 'javascript:alert(document.cookie)',
    ]);

    expect($notice)->not->toBeNull()
        ->and($notice->linkGithub)->toBeNull();
});

it('drops a data: scheme image url instead of passing it through', function (): void {
    $notice = NoticeEntry::fromArray([
        'id' => 3,
        'category' => 'welcome',
        'title' => 'Hi',
        'description' => 'Welcome',
        'image_url' => 'data:text/html,<script>alert(1)</script>',
    ]);

    expect($notice)->not->toBeNull()
        ->and($notice->imageUrl)->toBeNull();
});
