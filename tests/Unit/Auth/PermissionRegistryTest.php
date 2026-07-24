<?php

declare(strict_types=1);

use Magna\Auth\PermissionRegistry;

it('registers keys with and without descriptions', function (): void {
    $registry = new PermissionRegistry;
    $registry->register('blog.posts.create', 'Create blog posts');
    $registry->register('blog.posts.delete');

    expect($registry->has('blog.posts.create'))->toBeTrue()
        ->and($registry->has('blog.posts.delete'))->toBeTrue()
        ->and($registry->has('blog.posts.update'))->toBeFalse()
        ->and($registry->all())->toBe([
            'blog.posts.create' => 'Create blog posts',
            'blog.posts.delete' => null,
        ]);
});

it('registers many keys from a mixed list and map', function (): void {
    $registry = new PermissionRegistry;
    $registry->registerMany([
        'users.view' => 'View users',
        'users.manage',
    ]);

    expect($registry->has('users.view'))->toBeTrue()
        ->and($registry->has('users.manage'))->toBeTrue();
});

it('rejects invalid permission keys', function (string $key): void {
    $registry = new PermissionRegistry;

    expect(fn () => $registry->register($key))->toThrow(InvalidArgumentException::class);
})->with([
    'single segment' => 'blog',
    'uppercase' => 'Blog.Posts.Create',
    'wildcard' => 'blog.*',
    'empty segment' => 'blog..create',
    'trailing dot' => 'blog.posts.',
    'spaces' => 'blog.my posts.create',
]);
