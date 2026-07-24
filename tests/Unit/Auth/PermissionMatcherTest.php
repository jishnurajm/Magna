<?php

declare(strict_types=1);

use Magna\Auth\PermissionMatcher;

it('matches exact keys', function (): void {
    expect(PermissionMatcher::matches('blog.posts.create', 'blog.posts.create'))->toBeTrue();
});

it('is case sensitive and rejects different keys', function (): void {
    expect(PermissionMatcher::matches('blog.posts.create', 'blog.posts.delete'))->toBeFalse()
        ->and(PermissionMatcher::matches('Blog.Posts.Create', 'blog.posts.create'))->toBeFalse();
});

it('matches any remaining segments with a trailing wildcard', function (string $key, bool $expected): void {
    expect(PermissionMatcher::matches('blog.*', $key))->toBe($expected);
})->with([
    'deep key' => ['blog.posts.create', true],
    'single segment after prefix' => ['blog.settings', true],
    'bare prefix does not match' => ['blog', false],
    'different namespace' => ['shop.products.manage', false],
    'prefix is not a segment match' => ['blogging.posts', false],
]);

it('matches exactly one segment with a mid-position wildcard', function (string $key, bool $expected): void {
    expect(PermissionMatcher::matches('content.*.view', $key))->toBe($expected);
})->with([
    'one segment' => ['content.article.view', true],
    'wrong action' => ['content.article.publish', false],
    'two segments under wildcard' => ['content.a.b.view', false],
    'missing action' => ['content.article', false],
]);

it('matches everything with a bare wildcard', function (): void {
    expect(PermissionMatcher::matches('*', 'anything.at.all'))->toBeTrue()
        ->and(PermissionMatcher::matches('*', 'users.manage'))->toBeTrue();
});

it('rejects keys longer than a wildcard-free grant', function (): void {
    expect(PermissionMatcher::matches('blog.posts', 'blog.posts.create'))->toBeFalse();
});

it('reports whether any grant in a set matches', function (): void {
    $grants = ['users.view', 'blog.*'];

    expect(PermissionMatcher::anyMatches($grants, 'blog.posts.create'))->toBeTrue()
        ->and(PermissionMatcher::anyMatches($grants, 'users.view'))->toBeTrue()
        ->and(PermissionMatcher::anyMatches($grants, 'users.manage'))->toBeFalse()
        ->and(PermissionMatcher::anyMatches([], 'users.view'))->toBeFalse();
});
