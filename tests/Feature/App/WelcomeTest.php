<?php

declare(strict_types=1);

// The Filament admin panel is mounted at "/". Guests are redirected to the
// panel login page by its auth middleware.
it('redirects unauthenticated visitors from the root to the login page', function (): void {
    $this->get('/')->assertRedirect('/login');
});

it('serves the panel login page', function (): void {
    $this->get('/login')->assertOk();
});
